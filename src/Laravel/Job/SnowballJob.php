<?php

declare(strict_types=1);

namespace Nexus\Laravel\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpus;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpusHandler;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpusResult;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobStarted;
use Throwable;

final class SnowballJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SnowballCorpus $command,
    ) {}

    public function handle(SnowballCorpusHandler $handler, Dispatcher $events): SnowballCorpusResult
    {
        $context = $this->eventContext();
        $runId = $this->newRunId();
        $startedAt = hrtime(true);
        $events->dispatch(new NexusJobStarted($runId, 'snowball_corpus', self::class, $context));

        try {
            $result = $handler->handle($this->command);
            $events->dispatch(new NexusJobCompleted(
                runId: $runId,
                jobName: 'snowball_corpus',
                jobClass: self::class,
                context: $context,
                summary: [
                    'initial_seed_count' => $result->initialSeedCount,
                    'depth_reached' => $result->depthReached,
                    'round_count' => count($result->rounds),
                    'provider_stat_count' => count($result->providerStats),
                    'provider_failure_count' => $this->providerFailureCount($result),
                    'total_discovered' => $result->totalDiscoveredCount(),
                    'total_net_new' => $result->totalNetNewCount(),
                    'combined_count' => $result->combinedCorpus->count(),
                    'new_count' => $result->newCorpus->count(),
                ],
                durationMs: $this->elapsedMs($startedAt),
            ));

            return $result;
        } catch (Throwable $error) {
            $events->dispatch(new NexusJobFailed(
                runId: $runId,
                jobName: 'snowball_corpus',
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
            'project_id' => $this->command->projectId,
            'seed_count' => $this->command->seedCorpus->count(),
            'known_count' => $this->command->initialKnownCorpus()->count(),
            'forward' => $this->command->forward,
            'backward' => $this->command->backward,
            'depth' => $this->command->depth,
            'max_citations' => $this->command->maxCitations,
            'max_references' => $this->command->maxReferences,
            'provider_aliases' => $this->command->providerAliases,
        ];
    }

    private function providerFailureCount(SnowballCorpusResult $result): int
    {
        return count(array_filter(
            $result->providerStats,
            static fn ($stat): bool => $stat->failed(),
        ));
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
