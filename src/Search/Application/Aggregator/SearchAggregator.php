<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Aggregator;

use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\SearchQuery;
use Psr\Log\LoggerInterface;

final class SearchAggregator implements SearchAggregatorPort
{
    /** 
     * @param AcademicProviderPort[] $adapters 
     */
    public function __construct(
        private readonly array              $adapters,
        private readonly DeduplicationPort  $deduplication,
        private readonly ?LoggerInterface   $logger = null,
    ) {}

    public function aggregate(SearchQuery $query): AggregatedResult
    {
        $allWorks = [];
        $stats    = [];

        foreach ($this->adapters as $adapter) {
            $start = hrtime(true);

            try {
                $works         = $adapter->search($query);
                $allWorks[]    = $works;
                $latencyMs     = (hrtime(true) - $start) / 1_000_000;
                $stats[]       = new ProviderStat($adapter->alias(), count($works), $latencyMs);
            } catch (ProviderUnavailable $e) {
                $latencyMs = (hrtime(true) - $start) / 1_000_000;
                $stats[]   = new ProviderStat($adapter->alias(), 0, $latencyMs, $e->getMessage());
                $this->logger?->warning("Aggregator skipped {$adapter->alias()}", ['reason' => $e->getMessage()]);
            }
        }

        $merged  = array_merge(...$allWorks);
        $corpus  = CorpusSlice::fromWorksUnsafe(...$merged);
        $deduped = $this->deduplication->deduplicate($corpus);

        return new AggregatedResult(
            corpus:        $deduped,
            providerStats: $stats,
            totalRaw:      count($merged),
        );
    }
}
