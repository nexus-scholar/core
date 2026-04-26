<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Aggregator;

use Nexus\Search\Domain\CorpusSlice;

final readonly class AggregatedResult
{
    /**
     * @param CorpusSlice $corpus
     * @param ProviderStat[] $providerStats
     * @param int $totalRaw
     */
    public function __construct(
        public CorpusSlice $corpus,
        public array       $providerStats,
        public int         $totalRaw,
    ) {}
}
