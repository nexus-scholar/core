<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\Search\Domain\CorpusSlice;

final readonly class SnowballCorpusResult
{
    /**
     * @param list<SnowballRoundResult> $rounds
     * @param list<SnowballProviderStat> $providerStats
     */
    public function __construct(
        public string $projectId,
        public int $initialSeedCount,
        public int $depthReached,
        public CorpusSlice $combinedCorpus,
        public CorpusSlice $newCorpus,
        public array $rounds,
        public array $providerStats,
    ) {
    }

    public function totalDiscoveredCount(): int
    {
        return array_sum(array_map(
            static fn (SnowballRoundResult $round): int => $round->discoveredCount,
            $this->rounds,
        ));
    }

    public function totalNetNewCount(): int
    {
        return $this->newCorpus->count();
    }
}
