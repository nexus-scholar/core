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
use Nexus\CitationNetwork\Application\UseCase\SnowballProviderStat;
use Nexus\CitationNetwork\Application\UseCase\SnowballRoundResult;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobProgressed;
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
            $this->dispatchProgressEvents($events, $runId, $context, $result, $startedAt);
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

    /**
     * @param array<string, mixed> $baseContext
     */
    private function dispatchProgressEvents(
        Dispatcher $events,
        string $runId,
        array $baseContext,
        SnowballCorpusResult $result,
        float|int $startedAt,
    ): void {
        foreach ($result->rounds as $round) {
            $events->dispatch(new NexusJobProgressed(
                runId: $runId,
                jobName: 'snowball_corpus',
                jobClass: self::class,
                progressKey: "round:{$round->depth}",
                context: [
                    ...$baseContext,
                    'progress_type' => 'snowball_round',
                    'depth' => $round->depth,
                ],
                summary: $this->roundSummary($round),
                durationMs: $this->elapsedMs($startedAt),
            ));
        }

        foreach ($result->providerStats as $index => $stat) {
            $progressKey = sprintf(
                'provider:%d:%s:%s:%s',
                $index,
                $stat->alias,
                $stat->direction->value,
                substr(hash('sha256', $stat->seedWorkId), 0, 12),
            );
            $events->dispatch(new NexusJobProgressed(
                runId: $runId,
                jobName: 'snowball_corpus',
                jobClass: self::class,
                progressKey: $progressKey,
                context: [
                    ...$baseContext,
                    'progress_type' => 'snowball_provider',
                    'provider_alias' => $stat->alias,
                    'direction' => $stat->direction->value,
                    'seed_work_id' => $stat->seedWorkId,
                ],
                summary: $this->providerSummary($stat),
                durationMs: $this->elapsedMs($startedAt),
            ));
        }
    }

    /**
     * @return array<string, int>
     */
    private function roundSummary(SnowballRoundResult $round): array
    {
        return [
            'input_seed_count' => $round->inputSeedCount,
            'discovered_count' => $round->discoveredCount,
            'deduplicated_discovered_count' => $round->deduplicatedDiscoveredCount,
            'already_known_count' => $round->alreadyKnownCount,
            'net_new_count' => $round->netNewCount,
            'provider_failure_count' => $round->providerFailureCount,
        ];
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    private function providerSummary(SnowballProviderStat $stat): array
    {
        return [
            'result_count' => $stat->resultCount,
            'latency_ms' => $stat->latencyMs,
            'failed' => $stat->failed(),
            'error_message' => $stat->errorMessage,
            'skip_reason' => $stat->skipReason,
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
