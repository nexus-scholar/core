<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

final readonly class SnowballRoundResult
{
    public function __construct(
        public int $depth,
        public int $inputSeedCount,
        public int $discoveredCount,
        public int $deduplicatedDiscoveredCount,
        public int $alreadyKnownCount,
        public int $netNewCount,
        public int $providerFailureCount,
    ) {
    }
}
