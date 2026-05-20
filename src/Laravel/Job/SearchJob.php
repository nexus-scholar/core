<?php

declare(strict_types=1);

namespace Nexus\Laravel\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Search\Application\Plan\SearchPlan;
use Nexus\Search\Application\Plan\SearchPlanResult;
use Nexus\Search\Application\Plan\SearchPlanRunOptions;
use Nexus\Search\Application\Plan\SearchPlanRunner;
use Throwable;

final class SearchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SearchPlan $plan,
        public ?SearchPlanRunOptions $options = null,
    ) {}

    public function handle(SearchPlanRunner $runner, Dispatcher $events): SearchPlanResult
    {
        $context = $this->eventContext();
        $runId = $this->newRunId();
        $startedAt = hrtime(true);
        $events->dispatch(new NexusJobStarted($runId, 'search', self::class, $context));

        try {
            $result = $runner->run($this->plan, $this->options);
            $events->dispatch(new NexusJobCompleted(
                runId: $runId,
                jobName: 'search',
                jobClass: self::class,
                context: $context,
                summary: [
                    'query_count' => count($result->itemResults),
                    'success_count' => $result->successCount(),
                    'failure_count' => $result->failureCount(),
                    'total_raw' => $result->totalRaw(),
                    'total_unique' => $result->totalUnique(),
                ],
                durationMs: $this->elapsedMs($startedAt),
            ));

            return $result;
        } catch (Throwable $error) {
            $events->dispatch(new NexusJobFailed(
                runId: $runId,
                jobName: 'search',
                jobClass: self::class,
                context: $context,
                errorClass: $error::class,
                errorMessage: $error->getMessage(),
                durationMs: $this->elapsedMs($startedAt),
            ));

            throw $error;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function eventContext(): array
    {
        return [
            'project_id' => $this->options?->projectId ?? $this->plan->projectId,
            'source_name' => $this->plan->sourceName,
            'query_count' => $this->plan->count(),
            'only_ids' => $this->options?->onlyIds ?? [],
            'priority' => $this->options?->priority,
            'provider_aliases' => $this->options?->providerAliases ?? [],
        ];
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }

    private function newRunId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
