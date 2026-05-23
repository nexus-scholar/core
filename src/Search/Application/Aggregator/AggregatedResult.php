<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Aggregator;

use Nexus\Shared\Domain\CorpusSlice;

final readonly class AggregatedResult
{
    /**
     * @param  ProviderStat[]  $providerStats
     */
    public function __construct(
        public CorpusSlice $corpus,
        public array $providerStats,
        public int $totalRaw,
        public bool $fromCache = false,
        public int $durationMs = 0,
    ) {}
}
