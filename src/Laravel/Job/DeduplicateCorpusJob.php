<?php

declare(strict_types=1);

namespace Nexus\Laravel\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Deduplication\Application\DeduplicateCorpus;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Deduplication\Application\DeduplicateCorpusResult;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Shared\Domain\CorpusSlice;
use Throwable;

final class DeduplicateCorpusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string[]  $policyAliases
     */
    public function __construct(
        public CorpusSlice $corpus,
        public string $projectId = 'default-project',
        public array $policyAliases = [],
    ) {}

    public function handle(DeduplicateCorpusHandler $handler, Dispatcher $events): DeduplicateCorpusResult
    {
        $context = $this->eventContext();
        $runId = $this->newRunId();
        $startedAt = hrtime(true);
        $events->dispatch(new NexusJobStarted($runId, 'deduplicate_corpus', self::class, $context));

        try {
            $result = $handler->handle(new DeduplicateCorpus(
                corpus: $this->corpus,
                projectId: $this->projectId,
                policyAliases: $this->policyAliases,
            ));
            $events->dispatch(new NexusJobCompleted(
                runId: $runId,
                jobName: 'deduplicate_corpus',
                jobClass: self::class,
                context: $context,
                summary: [
                    'input_count' => $result->inputCount,
                    'unique_count' => $result->uniqueCount,
                    'duplicates_removed' => $result->duplicatesRemoved,
                    'policy_stats' => $result->policyStats,
                ],
                durationMs: $this->elapsedMs($startedAt),
            ));

            return $result;
        } catch (Throwable $error) {
            $events->dispatch(new NexusJobFailed(
                runId: $runId,
                jobName: 'deduplicate_corpus',
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
            'project_id' => $this->projectId,
            'input_count' => $this->corpus->count(),
            'policy_aliases' => $this->policyAliases,
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
