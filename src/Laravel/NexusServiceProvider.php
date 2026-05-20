<?php

declare(strict_types=1);

namespace Nexus\Laravel;

use Illuminate\Support\ServiceProvider;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\Port\AdapterCollection;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\RateLimit\TokenBucketRateLimiter;
use Nexus\Search\Infrastructure\Deduplication\DeduplicationAdapter;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Search\Application\Aggregator\SearchAggregator;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Application\Plan\SearchPlanParserPort;
use Nexus\Search\Application\Plan\SearchPlanRunner;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\Port\SearchRunRecorderPort;
use Nexus\Search\Application\UseCase\PersistentSearchRunner;
use Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler;
use Nexus\Search\Infrastructure\Plan\YamlSearchPlanParser;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\TransactionPort;
use Psr\Log\LoggerInterface;

final class NexusServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/nexus.php', 'nexus');

        $this->app->singleton(HttpClientPort::class, fn () => GuzzleHttpClient::create());
        $this->app->singleton(ProjectLockPort::class, \Nexus\Laravel\Persistence\EloquentProjectLock::class);
        $this->app->singleton(TransactionPort::class, \Nexus\Laravel\Persistence\LaravelTransaction::class);
        $this->app->singleton(JobLifecycleRecorderPort::class, \Nexus\Laravel\Persistence\EloquentJobLifecycleRecorder::class);

        $this->app->singleton(\Nexus\Search\Domain\Port\SearchCachePort::class, function ($app) {
            return new \Nexus\Laravel\Persistence\LaravelSearchCache($app['cache.store']);
        });

        $this->app->singleton('nexus.provider_configs', function ($app) {
            $config = $app['config']->get('nexus');

            return ProviderConfigRegistry::fromArray(
                $config['providers'] ?? [],
                $config['mail_to'] ?? null,
            );
        });

        // Deduplication Context
        $this->app->singleton(DeduplicateCorpusHandler::class, function ($app) {
            return new DeduplicateCorpusHandler(
                policies: [
                    new \Nexus\Deduplication\Infrastructure\DoiMatchPolicy(),
                    new \Nexus\Deduplication\Infrastructure\NamespaceMatchPolicy(\Nexus\Shared\ValueObject\WorkIdNamespace::ARXIV),
                    new \Nexus\Deduplication\Infrastructure\NamespaceMatchPolicy(\Nexus\Shared\ValueObject\WorkIdNamespace::OPENALEX),
                    new \Nexus\Deduplication\Infrastructure\NamespaceMatchPolicy(\Nexus\Shared\ValueObject\WorkIdNamespace::S2),
                    new \Nexus\Deduplication\Infrastructure\NamespaceMatchPolicy(\Nexus\Shared\ValueObject\WorkIdNamespace::PUBMED),
                    new \Nexus\Deduplication\Infrastructure\TitleFuzzyPolicy(
                        new \Nexus\Deduplication\Infrastructure\TitleNormalizer(),
                        95 // The constructor uses an integer threshold (e.g. 95)
                    ),
                ],
                electionPolicy: new \Nexus\Deduplication\Infrastructure\CompletenessElectionPolicy()
            );
        });

        $this->app->singleton(DeduplicationPort::class, DeduplicationAdapter::class);

        // Persistence repositories
        $this->app->singleton(
            \Nexus\Search\Domain\Port\WorkRepositoryPort::class,
            \Nexus\Laravel\Persistence\Repository\EloquentWorkRepository::class
        );
        $this->app->singleton(
            \Nexus\Search\Domain\Port\SearchQueryRepositoryPort::class,
            \Nexus\Laravel\Persistence\Repository\EloquentSearchQueryRepository::class
        );
        $this->app->singleton(
            SearchRunRecorderPort::class,
            \Nexus\Laravel\Persistence\EloquentSearchRunRecorder::class
        );
        $this->app->singleton(
            \Nexus\Deduplication\Domain\Port\ClusterRepositoryPort::class,
            \Nexus\Laravel\Persistence\Repository\EloquentDedupClusterRepository::class
        );
        $this->app->singleton(
            \Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort::class,
            \Nexus\Laravel\Persistence\Repository\EloquentCitationGraphRepository::class
        );
        $this->app->singleton(
            \Nexus\CitationNetwork\Domain\Port\GraphAlgorithmPort::class,
            \Nexus\CitationNetwork\Infrastructure\Graph\MbsoftNetworkMetricsCalculator::class
        );
        $this->app->singleton(
            \Nexus\CitationNetwork\Domain\Port\SnowballingProviderCollection::class,
            function ($app) {
                $configs = $app->make('nexus.provider_configs');
                $http = $app->make(HttpClientPort::class);
                $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;
                $providers = [];
                $factories = [
                    'openalex' => \Nexus\Search\Infrastructure\Provider\OpenAlexAdapter::class,
                    'crossref' => \Nexus\Search\Infrastructure\Provider\CrossrefAdapter::class,
                    'semantic_scholar' => \Nexus\Search\Infrastructure\Provider\SemanticScholarAdapter::class,
                ];

                foreach ($factories as $alias => $adapterClass) {
                    $config = $configs[$alias] ?? null;

                    if (! $config instanceof ProviderConfig || ! $config->enabled) {
                        continue;
                    }

                    $providers[] = new $adapterClass(
                        $http,
                        $this->rateLimiterFor($config),
                        $config,
                        $logger,
                    );
                }

                return new \Nexus\CitationNetwork\Domain\Port\SnowballingProviderCollection(...$providers);
            },
        );
        $this->app->singleton(\Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder::class);
        $this->app->singleton(\Nexus\CitationNetwork\Application\UseCase\BuildCitationGraphHandler::class);
        $this->app->singleton(\Nexus\CitationNetwork\Application\UseCase\AnalyzeNetworkHandler::class);
        $this->app->singleton(\Nexus\CitationNetwork\Application\UseCase\FindShortestCitationPathHandler::class);
        $this->app->singleton(\Nexus\CitationNetwork\Application\UseCase\SnowballCorpusHandler::class);

        // Search Aggregator
        $this->app->singleton(SearchAggregatorPort::class, function ($app) {
            $configs     = $app->make('nexus.provider_configs');
            $http        = $app->make(HttpClientPort::class);
            $logger      = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;
            $adapters    = $this->searchAdapters($configs, $http, $logger);

            return new SearchAggregator(
                new AdapterCollection(...$adapters),
                $app->make(DeduplicationPort::class),
                $app->make(\Nexus\Search\Domain\Port\SearchCachePort::class),
                $logger,
            );
        });

        $this->app->singleton(SearchAcrossProvidersHandler::class, function ($app) {
            return new SearchAcrossProvidersHandler(
                $app->make(SearchAggregatorPort::class),
                $app->make(ProjectLockPort::class),
            );
        });

        $this->app->singleton(SearchExecutorPort::class, function ($app) {
            return new PersistentSearchRunner(
                $app->make(SearchAcrossProvidersHandler::class),
                $app->make(SearchRunRecorderPort::class),
            );
        });

        $this->app->singleton(SearchPlanParserPort::class, YamlSearchPlanParser::class);
        $this->app->singleton(SearchPlanRunner::class);

        // Dissemination Module
        $this->app->singleton(\Nexus\Dissemination\Domain\Port\FileStoragePort::class, function ($app) {
            $disk = $app['config']->get('nexus.dissemination.pdf_storage_disk', 'public');
            return new \Nexus\Laravel\Persistence\LaravelFileStorage($disk);
        });

        $this->app->singleton(
            \Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort::class,
            \Nexus\Laravel\Persistence\EloquentPdfFetchRepository::class
        );

        $this->app->singleton(
            \Nexus\Dissemination\Domain\Port\PdfDownloaderPort::class,
            \Nexus\Dissemination\Infrastructure\PdfSource\GuzzlePdfDownloader::class
        );

        $this->app->singleton(\Nexus\Dissemination\Domain\Port\SerializerCollection::class, function ($app) {
            return new \Nexus\Dissemination\Domain\Port\SerializerCollection(
                new \Nexus\Dissemination\Infrastructure\Serializer\BibTexSerializer(),
                new \Nexus\Dissemination\Infrastructure\Serializer\CsvSerializer(),
                new \Nexus\Dissemination\Infrastructure\Serializer\JsonSerializer(),
            );
        });

        $this->app->singleton(\Nexus\Dissemination\Domain\Port\FullTextSourceCollection::class, function ($app) {
            $http = $app->make(HttpClientPort::class);
            $sourceConfig = $app['config']->get('nexus.full_text.sources', []);
            $sourceConfig = is_array($sourceConfig) ? $sourceConfig : [];
            $sources = [];

            if ($this->fullTextSourceEnabled($sourceConfig, 'direct')) {
                $sources[] = new \Nexus\Dissemination\Infrastructure\PdfSource\DirectPdfSource();
            }

            $unpaywall = $this->fullTextSourceConfig(
                $sourceConfig,
                'unpaywall',
                'https://api.unpaywall.org/v2',
                ['rate_limit' => 1.0, 'timeout' => 10, 'max_retries' => 2],
            );

            if ($unpaywall->enabled && $unpaywall->email !== null) {
                $sources[] = new \Nexus\Dissemination\Infrastructure\PdfSource\UnpaywallPdfSource(
                    new \Nexus\Dissemination\Infrastructure\PdfSource\OaHttpClient(
                        $http,
                        $this->fullTextRateLimiterFor($unpaywall),
                        $unpaywall,
                    ),
                );
            }

            $pmc = $this->fullTextSourceConfig(
                $sourceConfig,
                'pmc',
                'https://pmc.ncbi.nlm.nih.gov/api/oai/v1/mh',
                ['rate_limit' => 3.0, 'timeout' => 15, 'max_retries' => 2, 'prefer_pdf' => false, 'prefer_xml' => true],
            );

            if ($pmc->enabled) {
                $sources[] = new \Nexus\Dissemination\Infrastructure\PdfSource\PmcOaiFullTextSource(
                    new \Nexus\Dissemination\Infrastructure\PdfSource\OaHttpClient(
                        $http,
                        $this->fullTextRateLimiterFor($pmc),
                        $pmc,
                    ),
                );
            }

            $europePmc = $this->fullTextSourceConfig(
                $sourceConfig,
                'europe_pmc',
                'https://www.ebi.ac.uk/europepmc/webservices/rest',
                ['rate_limit' => 1.0, 'timeout' => 15, 'max_retries' => 2, 'prefer_pdf' => true, 'prefer_xml' => true],
            );

            if ($europePmc->enabled) {
                $sources[] = new \Nexus\Dissemination\Infrastructure\PdfSource\EuropePmcFullTextSource(
                    new \Nexus\Dissemination\Infrastructure\PdfSource\OaHttpClient(
                        $http,
                        $this->fullTextRateLimiterFor($europePmc),
                        $europePmc,
                    ),
                );
            }

            if ($this->fullTextSourceEnabled($sourceConfig, 'arxiv')) {
                $sources[] = new \Nexus\Dissemination\Infrastructure\PdfSource\ArXivPdfSource();
            }

            if ($this->fullTextSourceEnabled($sourceConfig, 'openalex')) {
                $sources[] = new \Nexus\Dissemination\Infrastructure\PdfSource\OpenAlexPdfSource();
            }

            if ($this->fullTextSourceEnabled($sourceConfig, 'semantic_scholar')) {
                $sources[] = new \Nexus\Dissemination\Infrastructure\PdfSource\SemanticScholarPdfSource();
            }

            return new \Nexus\Dissemination\Domain\Port\FullTextSourceCollection(
                ...$sources,
            );
        });
    }

    /**
     * @param array<string, ProviderConfig> $configs
     * @return array<int, \Nexus\Search\Domain\Port\AcademicProviderPort>
     */
    private function searchAdapters(array $configs, HttpClientPort $http, ?LoggerInterface $logger): array
    {
        $factories = [
            'arxiv' => fn (ProviderConfig $config) => new \Nexus\Search\Infrastructure\Provider\ArXivAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'crossref' => fn (ProviderConfig $config) => new \Nexus\Search\Infrastructure\Provider\CrossrefAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'doaj' => fn (ProviderConfig $config) => new \Nexus\Search\Infrastructure\Provider\DoajAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'ieee' => fn (ProviderConfig $config) => new \Nexus\Search\Infrastructure\Provider\IeeeAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'openalex' => fn (ProviderConfig $config) => new \Nexus\Search\Infrastructure\Provider\OpenAlexAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'pubmed' => fn (ProviderConfig $config) => new \Nexus\Search\Infrastructure\Provider\PubMedAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'semantic_scholar' => fn (ProviderConfig $config) => new \Nexus\Search\Infrastructure\Provider\SemanticScholarAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
        ];

        $adapters = [];

        foreach ($factories as $alias => $factory) {
            $config = $configs[$alias] ?? null;

            if (! $config instanceof ProviderConfig || ! $config->enabled) {
                continue;
            }

            $adapters[] = $factory($config);
        }

        return $adapters;
    }

    private function rateLimiterFor(ProviderConfig $config): TokenBucketRateLimiter
    {
        return new TokenBucketRateLimiter(
            ratePerSecond: $config->ratePerSecond,
            capacity: max(1.0, $config->ratePerSecond),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $sourceConfig
     * @param array<string, mixed> $defaults
     */
    private function fullTextSourceConfig(
        array $sourceConfig,
        string $alias,
        string $baseUrl,
        array $defaults,
    ): \Nexus\Dissemination\Infrastructure\PdfSource\FullTextSourceConfig {
        $values = $sourceConfig[$alias] ?? [];

        return \Nexus\Dissemination\Infrastructure\PdfSource\FullTextSourceConfig::fromArray(
            $alias,
            $baseUrl,
            is_array($values) ? $values : [],
            $defaults,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $sourceConfig
     */
    private function fullTextSourceEnabled(array $sourceConfig, string $alias): bool
    {
        $values = $sourceConfig[$alias] ?? [];
        if (! is_array($values) || ! array_key_exists('enabled', $values)) {
            return true;
        }

        if (is_bool($values['enabled'])) {
            return $values['enabled'];
        }

        return filter_var($values['enabled'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }

    private function fullTextRateLimiterFor(
        \Nexus\Dissemination\Infrastructure\PdfSource\FullTextSourceConfig $config,
    ): TokenBucketRateLimiter {
        return new TokenBucketRateLimiter(
            ratePerSecond: $config->ratePerSecond,
            capacity: max(1.0, $config->ratePerSecond),
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migration');

        $this->app['events']->listen(
            [
                \Nexus\Laravel\Event\NexusJobStarted::class,
                \Nexus\Laravel\Event\NexusJobCompleted::class,
                \Nexus\Laravel\Event\NexusJobFailed::class,
            ],
            \Nexus\Laravel\Listener\RecordNexusJobLifecycle::class,
        );

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/nexus.php' => $this->app->configPath('nexus.php'),
            ], 'nexus-config');

            $this->publishes([
                __DIR__.'/Migration' => $this->app->databasePath('migrations'),
            ], 'nexus-migrations');

            $this->commands([
                \Nexus\Laravel\Command\NexusSearchCommand::class,
            ]);
        }
    }
}
