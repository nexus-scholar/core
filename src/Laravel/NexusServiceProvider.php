<?php

declare(strict_types=1);

namespace Nexus\Laravel;

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder;
use Nexus\CitationNetwork\Application\UseCase\AnalyzeNetworkHandler;
use Nexus\CitationNetwork\Application\UseCase\BuildCitationGraphHandler;
use Nexus\CitationNetwork\Application\UseCase\FindShortestCitationPathHandler;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpusHandler;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Nexus\CitationNetwork\Domain\Port\GraphAlgorithmPort;
use Nexus\CitationNetwork\Domain\Port\SnowballingProviderCollection;
use Nexus\CitationNetwork\Infrastructure\Graph\MbsoftCitationGraphMapper;
use Nexus\CitationNetwork\Infrastructure\Graph\MbsoftNetworkMetricsCalculator;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\Deduplication\Infrastructure\CompletenessElectionPolicy;
use Nexus\Deduplication\Infrastructure\DoiMatchPolicy;
use Nexus\Deduplication\Infrastructure\NamespaceMatchPolicy;
use Nexus\Deduplication\Infrastructure\TitleFuzzyPolicy;
use Nexus\Deduplication\Infrastructure\TitleNormalizer;
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Application\UseCase\ExportCitationGraphHandler;
use Nexus\Dissemination\Application\UseCase\ExportNetworkHandler;
use Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler;
use Nexus\Dissemination\Domain\Port\CitationGraphSerializerCollection;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\NetworkSerializerCollection;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use Nexus\Dissemination\Infrastructure\PdfSource\ArXivPdfSource;
use Nexus\Dissemination\Infrastructure\PdfSource\DirectPdfSource;
use Nexus\Dissemination\Infrastructure\PdfSource\EuropePmcFullTextSource;
use Nexus\Dissemination\Infrastructure\PdfSource\FullTextSourceConfig;
use Nexus\Dissemination\Infrastructure\PdfSource\GuzzlePdfDownloader;
use Nexus\Dissemination\Infrastructure\PdfSource\OaHttpClient;
use Nexus\Dissemination\Infrastructure\PdfSource\OpenAlexPdfSource;
use Nexus\Dissemination\Infrastructure\PdfSource\PmcOaiFullTextSource;
use Nexus\Dissemination\Infrastructure\PdfSource\SemanticScholarPdfSource;
use Nexus\Dissemination\Infrastructure\PdfSource\UnpaywallPdfSource;
use Nexus\Dissemination\Infrastructure\Serializer\BibTexSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\CsvSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\CytoscapeSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\GexfSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\GraphMlSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\JsonlSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\JsonSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\MbsoftCitationGraphSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\RisSerializer;
use Nexus\Laravel\Command\NexusScreenCommand;
use Nexus\Laravel\Command\NexusSearchCommand;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobProgressed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Laravel\Listener\RecordNexusJobLifecycle;
use Nexus\Laravel\Persistence\EloquentCorpusSnapshotRepository;
use Nexus\Laravel\Persistence\EloquentExportHistoryReader;
use Nexus\Laravel\Persistence\EloquentExportHistoryRecorder;
use Nexus\Laravel\Persistence\EloquentFullTextFetchReader;
use Nexus\Laravel\Persistence\EloquentJobLifecycleReader;
use Nexus\Laravel\Persistence\EloquentJobLifecycleRecorder;
use Nexus\Laravel\Persistence\EloquentPdfFetchRepository;
use Nexus\Laravel\Persistence\EloquentProjectLock;
use Nexus\Laravel\Persistence\EloquentProjectWorkMembership;
use Nexus\Laravel\Persistence\EloquentScreeningWorkSource;
use Nexus\Laravel\Persistence\EloquentSearchRunRecorder;
use Nexus\Laravel\Persistence\LaravelFileStorage;
use Nexus\Laravel\Persistence\LaravelSearchCache;
use Nexus\Laravel\Persistence\LaravelTransaction;
use Nexus\Laravel\Persistence\Repository\EloquentCitationGraphRepository;
use Nexus\Laravel\Persistence\Repository\EloquentDedupClusterRepository;
use Nexus\Laravel\Persistence\Repository\EloquentScreeningDecisionRepository;
use Nexus\Laravel\Persistence\Repository\EloquentScreeningRunRepository;
use Nexus\Laravel\Persistence\Repository\EloquentScreeningVoteRepository;
use Nexus\Laravel\Persistence\Repository\EloquentSearchQueryRepository;
use Nexus\Laravel\Persistence\Repository\EloquentWorkRepository;
use Nexus\Screening\Application\Port\LlmClientPort;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningPromptRendererPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningVoteRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningWorkSourcePort;
use Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsHandler;
use Nexus\Screening\Application\UseCase\CompareScreeningRunsHandler;
use Nexus\Screening\Application\UseCase\ScreenCorpusHandler;
use Nexus\Screening\Application\UseCase\ScreenWorkHandler;
use Nexus\Screening\Domain\CouncilDecisionAggregator;
use Nexus\Screening\Infrastructure\Llm\DisabledLlmClient;
use Nexus\Screening\Infrastructure\Llm\OpenRouterLlmClient;
use Nexus\Screening\Infrastructure\Prompt\DefaultScreeningPromptRenderer;
use Nexus\Search\Application\Aggregator\SearchAggregator;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Application\Plan\SearchPlanParserPort;
use Nexus\Search\Application\Plan\SearchPlanRunner;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\Port\SearchRunRecorderPort;
use Nexus\Search\Application\UseCase\PersistentSearchRunner;
use Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler;
use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\Port\AdapterCollection;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\SearchCachePort;
use Nexus\Search\Domain\Port\SearchQueryRepositoryPort;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Search\Infrastructure\Deduplication\DeduplicationAdapter;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\Plan\YamlSearchPlanParser;
use Nexus\Search\Infrastructure\Provider\ArXivAdapter;
use Nexus\Search\Infrastructure\Provider\CrossrefAdapter;
use Nexus\Search\Infrastructure\Provider\DoajAdapter;
use Nexus\Search\Infrastructure\Provider\IeeeAdapter;
use Nexus\Search\Infrastructure\Provider\OpenAlexAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Infrastructure\Provider\PubMedAdapter;
use Nexus\Search\Infrastructure\Provider\SemanticScholarAdapter;
use Nexus\Search\Infrastructure\RateLimit\TokenBucketRateLimiter;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;
use Nexus\Shared\Port\JobLifecycleReaderPort;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\Port\TransactionPort;
use Nexus\Shared\ValueObject\WorkIdNamespace;
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
        $this->app->singleton(CorpusSnapshotRepositoryPort::class, EloquentCorpusSnapshotRepository::class);
        $this->app->singleton(ProjectLockPort::class, function ($app) {
            return new EloquentProjectLock($app->make(CorpusSnapshotRepositoryPort::class));
        });
        $this->app->singleton(ProjectLockLifecyclePort::class, fn ($app) => $app->make(ProjectLockPort::class));
        $this->app->singleton(EloquentProjectWorkMembership::class, function ($app) {
            return new EloquentProjectWorkMembership(
                $app->make(ProjectLockPort::class),
                $app->make(CorpusSnapshotRepositoryPort::class),
            );
        });
        $this->app->singleton(ProjectWorkMembershipPort::class, fn ($app) => $app->make(EloquentProjectWorkMembership::class));
        $this->app->singleton(ProjectCorpusWorksPort::class, fn ($app) => $app->make(EloquentProjectWorkMembership::class));
        $this->app->singleton(CorpusLockPolicy::class, function ($app) {
            return new CorpusLockPolicy(
                $app->make(ProjectLockPort::class),
                $app->make(ProjectWorkMembershipPort::class),
                $app->make(ProjectLockLifecyclePort::class),
                $app->make(CorpusSnapshotRepositoryPort::class),
            );
        });
        $this->app->singleton(TransactionPort::class, LaravelTransaction::class);
        $this->app->singleton(JobLifecycleRecorderPort::class, EloquentJobLifecycleRecorder::class);
        $this->app->singleton(JobLifecycleReaderPort::class, EloquentJobLifecycleReader::class);

        $this->app->singleton(SearchCachePort::class, function ($app) {
            return new LaravelSearchCache($app['cache.store']);
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
                    new DoiMatchPolicy,
                    new NamespaceMatchPolicy(WorkIdNamespace::ARXIV),
                    new NamespaceMatchPolicy(WorkIdNamespace::OPENALEX),
                    new NamespaceMatchPolicy(WorkIdNamespace::S2),
                    new NamespaceMatchPolicy(WorkIdNamespace::PUBMED),
                    new TitleFuzzyPolicy(
                        new TitleNormalizer,
                        95 // The constructor uses an integer threshold (e.g. 95)
                    ),
                ],
                electionPolicy: new CompletenessElectionPolicy,
                lockPolicy: $app->make(CorpusLockPolicy::class),
            );
        });

        $this->app->singleton(DeduplicationPort::class, DeduplicationAdapter::class);

        // Persistence repositories
        $this->app->singleton(
            WorkRepositoryPort::class,
            EloquentWorkRepository::class
        );
        $this->app->singleton(
            SearchQueryRepositoryPort::class,
            EloquentSearchQueryRepository::class
        );
        $this->app->singleton(
            SearchRunRecorderPort::class,
            EloquentSearchRunRecorder::class
        );
        $this->app->singleton(
            ClusterRepositoryPort::class,
            EloquentDedupClusterRepository::class
        );
        $this->app->singleton(
            CitationGraphRepositoryPort::class,
            EloquentCitationGraphRepository::class
        );
        $this->app->singleton(
            ScreeningRunRepositoryPort::class,
            EloquentScreeningRunRepository::class
        );
        $this->app->singleton(
            ScreeningDecisionRepositoryPort::class,
            EloquentScreeningDecisionRepository::class
        );
        $this->app->singleton(
            ScreeningVoteRepositoryPort::class,
            EloquentScreeningVoteRepository::class
        );
        $this->app->singleton(
            ScreeningWorkSourcePort::class,
            EloquentScreeningWorkSource::class
        );
        $this->app->singleton(CouncilDecisionAggregator::class);
        $this->app->singleton(
            ScreeningPromptRendererPort::class,
            DefaultScreeningPromptRenderer::class
        );
        $this->app->singleton(LlmClientPort::class, function ($app) {
            $config = $app['config']->get('nexus.screening.llm', []);
            $config = is_array($config) ? $config : [];

            if (! $this->configBool($config['enabled'] ?? false)) {
                return new DisabledLlmClient('LLM screening is disabled.');
            }

            $provider = (string) ($config['provider'] ?? 'openrouter');
            if ($provider !== 'openrouter') {
                return new DisabledLlmClient("Unsupported LLM screening provider {$provider}.");
            }

            $openRouter = $config['openrouter'] ?? [];
            $openRouter = is_array($openRouter) ? $openRouter : [];
            $apiKey = (string) ($openRouter['api_key'] ?? '');

            if (trim($apiKey) === '') {
                return new DisabledLlmClient('OpenRouter API key is not configured.');
            }

            $caPath = CaBundle::getSystemCaRootBundlePath();

            return new OpenRouterLlmClient(
                http: new Client([
                    'timeout' => (int) ($config['timeout'] ?? 45),
                    'verify' => $caPath !== '' ? $caPath : true,
                ]),
                apiKey: $apiKey,
                baseUrl: (string) ($openRouter['base_url'] ?? 'https://openrouter.ai/api/v1'),
                timeoutSeconds: (int) ($config['timeout'] ?? 45),
                referer: isset($openRouter['referer']) ? (string) $openRouter['referer'] : null,
                appName: isset($openRouter['app_name']) ? (string) $openRouter['app_name'] : 'Nexus Scholar',
            );
        });
        $this->app->singleton(ScreenWorkHandler::class, function ($app) {
            return new ScreenWorkHandler(
                $app->make(LlmClientPort::class),
                $app->make(ScreeningPromptRendererPort::class),
                $app->make(CouncilDecisionAggregator::class),
                $app->make(ScreeningDecisionRepositoryPort::class),
                $app->make(ScreeningVoteRepositoryPort::class),
                $app->make(CorpusLockPolicy::class),
            );
        });
        $this->app->singleton(ScreenCorpusHandler::class, function ($app) {
            return new ScreenCorpusHandler(
                $app->make(ScreeningWorkSourcePort::class),
                $app->make(ScreeningRunRepositoryPort::class),
                $app->make(ScreenWorkHandler::class),
                $app->make(CorpusLockPolicy::class),
            );
        });
        $this->app->singleton(AdjudicateScreeningDecisionsHandler::class, function ($app) {
            return new AdjudicateScreeningDecisionsHandler(
                $app->make(ScreeningRunRepositoryPort::class),
                $app->make(ScreeningDecisionRepositoryPort::class),
                $app->make(CorpusLockPolicy::class),
            );
        });
        $this->app->singleton(CompareScreeningRunsHandler::class);
        $this->app->singleton(
            GraphAlgorithmPort::class,
            MbsoftNetworkMetricsCalculator::class
        );
        $this->app->singleton(
            SnowballingProviderCollection::class,
            function ($app) {
                $configs = $app->make('nexus.provider_configs');
                $http = $app->make(HttpClientPort::class);
                $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;
                $providers = [];
                $factories = [
                    'openalex' => OpenAlexAdapter::class,
                    'crossref' => CrossrefAdapter::class,
                    'semantic_scholar' => SemanticScholarAdapter::class,
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

                return new SnowballingProviderCollection(...$providers);
            },
        );
        $this->app->singleton(CitationGraphBuilder::class);
        $this->app->singleton(BuildCitationGraphHandler::class, function ($app) {
            return new BuildCitationGraphHandler(
                $app->make(CitationGraphBuilder::class),
                $app->make(CitationGraphRepositoryPort::class),
                $app->make(CorpusLockPolicy::class),
            );
        });
        $this->app->singleton(AnalyzeNetworkHandler::class);
        $this->app->singleton(FindShortestCitationPathHandler::class);
        $this->app->singleton(SnowballCorpusHandler::class, function ($app) {
            return new SnowballCorpusHandler(
                $app->make(SnowballingProviderCollection::class),
                $app->make(DeduplicationPort::class),
                $app->make(CorpusLockPolicy::class),
            );
        });

        // Search Aggregator
        $this->app->singleton(SearchAggregatorPort::class, function ($app) {
            $configs = $app->make('nexus.provider_configs');
            $http = $app->make(HttpClientPort::class);
            $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;
            $adapters = $this->searchAdapters($configs, $http, $logger);

            return new SearchAggregator(
                new AdapterCollection(...$adapters),
                $app->make(DeduplicationPort::class),
                $app->make(SearchCachePort::class),
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
                $app->make(ProjectLockPort::class),
            );
        });

        $this->app->singleton(SearchPlanParserPort::class, YamlSearchPlanParser::class);
        $this->app->singleton(SearchPlanRunner::class);

        // Dissemination Module
        $this->app->singleton(FileStoragePort::class, function ($app) {
            $disk = $app['config']->get('nexus.dissemination.pdf_storage_disk', 'public');

            return new LaravelFileStorage($disk);
        });

        $this->app->singleton(
            ExportHistoryPort::class,
            EloquentExportHistoryRecorder::class
        );

        $this->app->singleton(
            ExportHistoryReaderPort::class,
            EloquentExportHistoryReader::class
        );

        $this->app->singleton(FullTextFetchReaderPort::class, function ($app) {
            return new EloquentFullTextFetchReader($app->make(ProjectCorpusWorksPort::class));
        });

        $this->app->singleton(
            PdfFetchRepositoryPort::class,
            EloquentPdfFetchRepository::class
        );

        $this->app->singleton(
            PdfDownloaderPort::class,
            GuzzlePdfDownloader::class
        );

        $this->app->singleton(RetrieveFullTextHandler::class, function ($app) {
            return new RetrieveFullTextHandler(
                $app->make(FullTextSourceCollection::class),
                $app->make(FileStoragePort::class),
                $app->make(PdfDownloaderPort::class),
                $app->make(PdfFetchRepositoryPort::class),
                $app->make(CorpusLockPolicy::class),
            );
        });

        $this->app->singleton(SerializerCollection::class, function ($app) {
            return new SerializerCollection(
                new BibTexSerializer,
                new RisSerializer,
                new CsvSerializer,
                new JsonSerializer,
                new JsonlSerializer,
            );
        });

        $this->app->singleton(NetworkSerializerCollection::class, function ($app) {
            return new NetworkSerializerCollection(
                new CytoscapeSerializer,
                new GraphMlSerializer,
                new GexfSerializer,
            );
        });

        $this->app->singleton(CitationGraphSerializerCollection::class, function ($app) {
            return new CitationGraphSerializerCollection(
                new MbsoftCitationGraphSerializer(
                    $app->make(MbsoftCitationGraphMapper::class),
                ),
            );
        });

        $this->app->singleton(ExportBibliographyHandler::class, function ($app) {
            return new ExportBibliographyHandler(
                $app->make(SerializerCollection::class),
                $app->make(FileStoragePort::class),
                $app->make(ExportHistoryPort::class),
                $app->make(CorpusLockPolicy::class),
            );
        });

        $this->app->singleton(ExportNetworkHandler::class, function ($app) {
            return new ExportNetworkHandler(
                $app->make(NetworkSerializerCollection::class),
                $app->make(FileStoragePort::class),
                $app->make(ExportHistoryPort::class),
            );
        });

        $this->app->singleton(ExportCitationGraphHandler::class, function ($app) {
            return new ExportCitationGraphHandler(
                $app->make(CitationGraphSerializerCollection::class),
                $app->make(FileStoragePort::class),
                $app->make(ExportHistoryPort::class),
            );
        });

        $this->app->singleton(FullTextSourceCollection::class, function ($app) {
            $http = $app->make(HttpClientPort::class);
            $sourceConfig = $app['config']->get('nexus.full_text.sources', []);
            $sourceConfig = is_array($sourceConfig) ? $sourceConfig : [];
            $sources = [];

            if ($this->fullTextSourceEnabled($sourceConfig, 'direct')) {
                $sources[] = new DirectPdfSource;
            }

            $unpaywall = $this->fullTextSourceConfig(
                $sourceConfig,
                'unpaywall',
                'https://api.unpaywall.org/v2',
                ['rate_limit' => 1.0, 'timeout' => 10, 'max_retries' => 2],
            );

            if ($unpaywall->enabled && $unpaywall->email !== null) {
                $sources[] = new UnpaywallPdfSource(
                    new OaHttpClient(
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
                $sources[] = new PmcOaiFullTextSource(
                    new OaHttpClient(
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
                $sources[] = new EuropePmcFullTextSource(
                    new OaHttpClient(
                        $http,
                        $this->fullTextRateLimiterFor($europePmc),
                        $europePmc,
                    ),
                );
            }

            if ($this->fullTextSourceEnabled($sourceConfig, 'arxiv')) {
                $sources[] = new ArXivPdfSource;
            }

            if ($this->fullTextSourceEnabled($sourceConfig, 'openalex')) {
                $sources[] = new OpenAlexPdfSource;
            }

            if ($this->fullTextSourceEnabled($sourceConfig, 'semantic_scholar')) {
                $sources[] = new SemanticScholarPdfSource;
            }

            return new FullTextSourceCollection(
                ...$sources,
            );
        });
    }

    /**
     * @param  array<string, ProviderConfig>  $configs
     * @return array<int, AcademicProviderPort>
     */
    private function searchAdapters(array $configs, HttpClientPort $http, ?LoggerInterface $logger): array
    {
        $factories = [
            'arxiv' => fn (ProviderConfig $config) => new ArXivAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'crossref' => fn (ProviderConfig $config) => new CrossrefAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'doaj' => fn (ProviderConfig $config) => new DoajAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'ieee' => fn (ProviderConfig $config) => new IeeeAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'openalex' => fn (ProviderConfig $config) => new OpenAlexAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'pubmed' => fn (ProviderConfig $config) => new PubMedAdapter(
                $http,
                $this->rateLimiterFor($config),
                $config,
                $logger,
            ),
            'semantic_scholar' => fn (ProviderConfig $config) => new SemanticScholarAdapter(
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
     * @param  array<string, array<string, mixed>>  $sourceConfig
     * @param  array<string, mixed>  $defaults
     */
    private function fullTextSourceConfig(
        array $sourceConfig,
        string $alias,
        string $baseUrl,
        array $defaults,
    ): FullTextSourceConfig {
        $values = $sourceConfig[$alias] ?? [];

        return FullTextSourceConfig::fromArray(
            $alias,
            $baseUrl,
            is_array($values) ? $values : [],
            $defaults,
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $sourceConfig
     */
    private function fullTextSourceEnabled(array $sourceConfig, string $alias): bool
    {
        $values = $sourceConfig[$alias] ?? [];
        if (! is_array($values) || ! array_key_exists('enabled', $values)) {
            return true;
        }

        return $this->configBool($values['enabled'], true);
    }

    private function configBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function fullTextRateLimiterFor(
        FullTextSourceConfig $config,
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
                NexusJobStarted::class,
                NexusJobProgressed::class,
                NexusJobCompleted::class,
                NexusJobFailed::class,
            ],
            RecordNexusJobLifecycle::class,
        );

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/nexus.php' => $this->app->configPath('nexus.php'),
            ], 'nexus-config');

            $this->publishes([
                __DIR__.'/Migration' => $this->app->databasePath('migrations'),
            ], 'nexus-migrations');

            $this->commands([
                NexusSearchCommand::class,
                NexusScreenCommand::class,
            ]);
        }
    }
}
