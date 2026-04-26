<?php

declare(strict_types=1);

namespace Nexus\Search\Application;

use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\Port\SearchCachePort;

/**
 * Orchestrates a search across one or more academic providers.
 *
 * - Results are cached by the authoritative SearchQuery::cacheKey().
 * - One provider failing does NOT stop others (fault-isolated per provider).
 * - Domain events are emitted for each provider result.
 */
final class SearchAcrossProvidersHandler
{
    public function __construct(
        /** @var AcademicProviderPort[] */
        private readonly array           $providers,
        private readonly SearchCachePort $cache,
        private readonly int             $cacheTtl = 3600,
    ) {}

    public function handle(SearchAcrossProviders $command): SearchAcrossProvidersResult
    {
        $startTime = hrtime(true);

        // Determine which providers to use
        $providers = $this->resolveProviders($command->providerAliases);

        $sortedAliases = array_map(fn (AcademicProviderPort $p) => $p->alias(), $providers);
        sort($sortedAliases);

        $cacheKey = $command->query->cacheKey($sortedAliases);

        // Return cached result if available
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            $corpus = CorpusSlice::empty();

            foreach ($cached as $work) {
                $corpus->addWork($work);
            }

            return new SearchAcrossProvidersResult(
                corpus:          $corpus,
                providerResults: [],
                fromCache:       true,
                durationMs:      $this->elapsedMs($startTime),
            );
        }

        // Execute search across all selected providers
        $corpus          = CorpusSlice::empty();
        $providerResults = [];

        foreach ($providers as $provider) {
            $providerStart = hrtime(true);

            try {
                $works = $provider->search($command->query);

                foreach ($works as $work) {
                    $corpus->addWork($work);
                }

                $providerResults[] = new ProviderSearchResult(
                    providerAlias: $provider->alias(),
                    resultCount:   count($works),
                    success:       true,
                    durationMs:    $this->elapsedMs($providerStart),
                );
            } catch (ProviderUnavailable $e) {
                $providerResults[] = new ProviderSearchResult(
                    providerAlias: $provider->alias(),
                    resultCount:   0,
                    success:       false,
                    error:         $e->getMessage(),
                    durationMs:    $this->elapsedMs($providerStart),
                );
            }
        }

        // Cache the flat work array
        $this->cache->put($cacheKey, $corpus->all(), $this->cacheTtl);

        return new SearchAcrossProvidersResult(
            corpus:          $corpus,
            providerResults: $providerResults,
            fromCache:       false,
            durationMs:      $this->elapsedMs($startTime),
        );
    }

    /**
     * @param string[] $aliases empty = return all providers
     * @return AcademicProviderPort[]
     */
    private function resolveProviders(array $aliases): array
    {
        if ($aliases === []) {
            return $this->providers;
        }

        return array_values(array_filter(
            $this->providers,
            fn (AcademicProviderPort $p) => in_array($p->alias(), $aliases, true),
        ));
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }
}
