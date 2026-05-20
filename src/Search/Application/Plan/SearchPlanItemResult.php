<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Plan;

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Throwable;

final readonly class SearchPlanItemResult
{
    public function __construct(
        public SearchPlanItem $item,
        public ?AggregatedResult $result,
        public ?Throwable $error,
        public int $durationMs,
    ) {}

    public function succeeded(): bool
    {
        return $this->error === null;
    }
}
