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
            \Nexus\Deduplication\Domain\Port\ClusterRepositoryPort::class,
            \Nexus\Laravel\Persistence\Repository\EloquentDedupClusterRepository::class
        );
        $this->app->singleton(
            \Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort::class,
            \Nexus\Laravel\Persistence\Repository\EloquentCitationGraphRepository::class
        );

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
            return new \Nexus\Dissemination\Domain\Port\FullTextSourceCollection(
                new \Nexus\Dissemination\Infrastructure\PdfSource\ArXivPdfSource(),
                new \Nexus\Dissemination\Infrastructure\PdfSource\OpenAlexPdfSource(),
                new \Nexus\Dissemination\Infrastructure\PdfSource\SemanticScholarPdfSource(),
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
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migration');

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
