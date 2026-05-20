<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Plan;

use Nexus\Search\Application\Port\SearchExecutorPort;
use Throwable;

final class SearchPlanRunner
{
    public function __construct(
        private readonly SearchExecutorPort $executor,
    ) {}

    public function run(SearchPlan $plan, ?SearchPlanRunOptions $options = null): SearchPlanResult
    {
        $options ??= new SearchPlanRunOptions();
        $startedAt = hrtime(true);
        $itemResults = [];

        foreach ($plan->select($options) as $item) {
            $itemStartedAt = hrtime(true);

            try {
                $result = $this->executor->handle($item->toSearchCommand());
                $itemResults[] = new SearchPlanItemResult(
                    item: $item,
                    result: $result,
                    error: null,
                    durationMs: $this->elapsedMs($itemStartedAt),
                );
            } catch (Throwable $error) {
                if (! $options->continueOnFailure) {
                    throw $error;
                }

                $itemResults[] = new SearchPlanItemResult(
                    item: $item,
                    result: null,
                    error: $error,
                    durationMs: $this->elapsedMs($itemStartedAt),
                );
            }
        }

        return new SearchPlanResult(
            plan: $plan,
            itemResults: $itemResults,
            durationMs: $this->elapsedMs($startedAt),
        );
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }
}
