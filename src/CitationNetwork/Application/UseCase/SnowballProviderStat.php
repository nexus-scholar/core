<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Domain\SnowballDirection;

final readonly class SnowballProviderStat
{
    public function __construct(
        public string $alias,
        public SnowballDirection $direction,
        public string $seedWorkId,
        public int $resultCount,
        public float $latencyMs,
        public ?string $errorMessage = null,
        public ?string $skipReason = null,
    ) {
    }

    public function failed(): bool
    {
        return $this->errorMessage !== null;
    }
}
