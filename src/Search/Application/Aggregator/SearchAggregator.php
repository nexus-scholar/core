<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Aggregator;

use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Port\AdapterCollection;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\Port\SearchCachePort;
use Nexus\Search\Domain\SearchQuery;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Promise\Utils;
use Throwable;

final class SearchAggregator implements SearchAggregatorPort
{
    public function __construct(
        private readonly AdapterCollection $adapters,
        private readonly DeduplicationPort $deduplication,
        private readonly SearchCachePort   $cache,
        private readonly ?LoggerInterface  $logger = null,
        private readonly int               $cacheTtl = 3600,
    ) {}

    public function aggregate(SearchQuery $query): AggregatedResult
    {
        $startTime = hrtime(true);

        // Build list of active adapters
        $activeAdapters = $this->adapters->all();
        $sortedAliases  = array_map(fn ($p) => $p->alias(), $activeAdapters);
        sort($sortedAliases);

        $cacheKey = $query->cacheKey($sortedAliases);

        // 1. Check cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            $worksData = $cached['works'] ?? [];
            $statsData = $cached['stats'] ?? [];
            
            $works = array_map(
                fn (array $d) => \Nexus\Search\Application\Dto\ScholarlyWorkDto::toDomain($d), 
                $worksData
            );
            
            $corpus = CorpusSlice::fromWorksUnsafe(...$works);
            
            // Restore stats
            $stats = array_map(function (array $s) {
                return new ProviderStat(
                    $s['alias'],
                    $s['count'],
                    $s['ms'],
                    $s['error'] ?? null
                );
            }, $statsData);
            
            return new AggregatedResult(
                corpus:        $corpus,
                providerStats: $stats,
                totalRaw:      $cached['total_raw'] ?? $corpus->count(),
                fromCache:     true,
                durationMs:    $this->elapsedMs($startTime),
            );
        }

        // 2. Execute parallel search
        $promises = [];
        foreach ($activeAdapters as $adapter) {
            $alias = $adapter->alias();
            $providerStart = hrtime(true);
            
            $promises[$alias] = $adapter->searchAsync($query)->then(
                function ($works) use ($providerStart) {
                    return [
                        'success' => true,
                        'works'   => $works,
                        'ms'      => $this->elapsedMs($providerStart),
                    ];
                },
                function (Throwable $e) use ($providerStart) {
                    return [
                        'success' => false,
                        'error'   => $e->getMessage(),
                        'ms'      => $this->elapsedMs($providerStart),
                    ];
                }
            );
        }

        $settled = Utils::settle($promises)->wait();

        $allWorks = [];
        $stats    = [];

        foreach ($activeAdapters as $adapter) {
            $alias  = $adapter->alias();
            $result = $settled[$alias]['value'] ?? null; // Since we catch in the promise, state is always fulfilled

            if ($result === null) {
                // Should theoretically not happen unless settle is misused
                $stats[] = new ProviderStat($alias, 0, $this->elapsedMs($startTime), "Unknown error settling promise");
                continue;
            }

            if ($result['success']) {
                $works = $result['works'];
                array_push($allWorks, ...$works);
                $stats[] = new ProviderStat($alias, count($works), $result['ms']);
            } else {
                $error = $result['error'];
                $stats[] = new ProviderStat($alias, 0, $result['ms'], $error);
                $this->logger?->warning("Aggregator skipped {$alias}", ['reason' => $error]);
            }
        }

        if ($allWorks === []) {
            return new AggregatedResult(
                corpus:        CorpusSlice::empty(),
                providerStats: $stats,
                totalRaw:      0,
                fromCache:     false,
                durationMs:    $this->elapsedMs($startTime),
            );
        }

        // 3. Deduplicate and construct final corpus
        $rawCorpus = CorpusSlice::fromWorksUnsafe(...$allWorks);
        $deduped   = $this->deduplication->deduplicate($rawCorpus);

        // 4. Cache normalized form with stats
        $cachePayload = [
            'works'     => array_map(fn ($w) => \Nexus\Search\Application\Dto\ScholarlyWorkDto::fromDomain($w), $deduped->all()),
            'stats'     => array_map(fn ($s) => [
                'alias' => $s->alias,
                'count' => $s->resultCount,
                'ms'    => $s->latencyMs,
                'error' => $s->skipReason,
            ], $stats),
            'total_raw' => count($allWorks),
        ];

        $this->cache->put($cacheKey, $cachePayload, $this->cacheTtl);

        return new AggregatedResult(
            corpus:        $deduped,
            providerStats: $stats,
            totalRaw:      count($allWorks),
            fromCache:     false,
            durationMs:    $this->elapsedMs($startTime),
        );
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }
}
