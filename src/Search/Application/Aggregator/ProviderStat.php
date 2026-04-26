<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Aggregator;

final readonly class ProviderStat
{
    public function __construct(
        public string   $alias,
        public int      $resultCount,
        public float    $latencyMs,
        public ?string  $skipReason = null,
    ) {}
}
