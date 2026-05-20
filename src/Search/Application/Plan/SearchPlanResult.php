<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Plan;

final readonly class SearchPlanResult
{
    /**
     * @param list<SearchPlanItemResult> $itemResults
     */
    public function __construct(
        public SearchPlan $plan,
        public array $itemResults,
        public int $durationMs,
    ) {}

    public function failureCount(): int
    {
        return count(array_filter(
            $this->itemResults,
            static fn (SearchPlanItemResult $result): bool => ! $result->succeeded(),
        ));
    }

    public function successCount(): int
    {
        return count($this->itemResults) - $this->failureCount();
    }

    public function hasFailures(): bool
    {
        return $this->failureCount() > 0;
    }

    public function totalRaw(): int
    {
        return array_reduce(
            $this->itemResults,
            static fn (int $carry, SearchPlanItemResult $item): int => $carry + ($item->result?->totalRaw ?? 0),
            0,
        );
    }

    public function totalUnique(): int
    {
        return array_reduce(
            $this->itemResults,
            static fn (int $carry, SearchPlanItemResult $item): int => $carry + ($item->result?->corpus->count() ?? 0),
            0,
        );
    }
}
