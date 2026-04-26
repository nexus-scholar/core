<?php

declare(strict_types=1);

namespace Nexus\Laravel;

use Illuminate\Support\ServiceProvider;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\Port\AdapterCollection;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\RateLimit\NullRateLimiter;
use Nexus\Search\Infrastructure\Deduplication\DeduplicationAdapter;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Search\Application\Aggregator\SearchAggregator;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;

final class NexusServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/nexus.php', 'nexus');

        $this->app->singleton(HttpClientPort::class, fn () => GuzzleHttpClient::create());
        $this->app->singleton(RateLimiterPort::class, fn () => new NullRateLimiter());
        $this->app->singleton(\Nexus\Search\Domain\Port\SearchCachePort::class, \Nexus\Search\Infrastructure\Cache\NullSearchCache::class);

        $this->app->singleton('nexus.provider_configs', function ($app) {
            $config = $app['config']->get('nexus');

            return ProviderConfigRegistry::defaults(
                ieeeApiKey: $config['providers']['ieee']['api_key'] ?? null,
                s2ApiKey: $config['providers']['semantic_scholar']['api_key'] ?? null,
                pubmedApiKey: $config['providers']['pubmed']['api_key'] ?? null,
                mailTo: $config['mail_to'] ?? null,
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

        // Search Aggregator
        $this->app->singleton(SearchAggregatorPort::class, function ($app) {
            $configs     = $app->make('nexus.provider_configs');
            $http        = $app->make(HttpClientPort::class);
            $rateLimiter = $app->make(RateLimiterPort::class);

            $adapters = [
                new \Nexus\Search\Infrastructure\Provider\ArXivAdapter($http, $rateLimiter, $configs['arxiv']),
                new \Nexus\Search\Infrastructure\Provider\CrossrefAdapter($http, $rateLimiter, $configs['crossref']),
                new \Nexus\Search\Infrastructure\Provider\DoajAdapter($http, $rateLimiter, $configs['doaj']),
                new \Nexus\Search\Infrastructure\Provider\IeeeAdapter($http, $rateLimiter, $configs['ieee']),
                new \Nexus\Search\Infrastructure\Provider\OpenAlexAdapter($http, $rateLimiter, $configs['openalex']),
                new \Nexus\Search\Infrastructure\Provider\PubMedAdapter($http, $rateLimiter, $configs['pubmed']),
                new \Nexus\Search\Infrastructure\Provider\SemanticScholarAdapter($http, $rateLimiter, $configs['semantic_scholar']),
            ];

            return new SearchAggregator(
                new AdapterCollection(...$adapters),
                $app->make(DeduplicationPort::class),
                $app->make(\Nexus\Search\Domain\Port\SearchCachePort::class),
                $app->bound(\Psr\Log\LoggerInterface::class) ? $app->make(\Psr\Log\LoggerInterface::class) : null
            );
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/nexus.php' => $this->app->configPath('nexus.php'),
            ], 'nexus-config');

            $this->commands([
                \Nexus\Laravel\Command\NexusSearchCommand::class,
            ]);
        }
    }
}
