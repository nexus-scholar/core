<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningWorkSourcePort;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningVerdict;
use Nexus\Screening\Domain\ScreeningWork;
use Throwable;

final readonly class ScreenCorpusHandler
{
    public function __construct(
        private ScreeningWorkSourcePort $source,
        private ScreeningRunRepositoryPort $runs,
        private ScreenWorkHandler $screenWork,
    ) {}

    public function handle(ScreenCorpusCommand $command): ScreenCorpusResult
    {
        $startedAt = hrtime(true);
        $runId = $command->runId();

        $this->runs->start(ScreeningRun::start(
            id: $runId,
            projectId: $command->projectId,
            stage: $command->stage,
            mode: $command->mode,
            criteria: $command->criteria,
            name: $command->name,
            config: $command->runConfig(),
            source: $command->runSource(),
        ));

        try {
            $works = $this->source->forProject(
                projectId: $command->projectId,
                limit: $command->limit,
                workIds: $command->workIds,
                queryIds: $command->queryIds,
            );
        } catch (Throwable $error) {
            $this->runs->fail($runId, $error->getMessage());

            throw $error;
        }

        $verdicts = [];
        $failures = [];

        foreach ($works as $work) {
            try {
                $verdicts[] = $this->screenWork->handle($this->screenWorkCommand($command, $runId, $work));
            } catch (Throwable $error) {
                if (! $command->continueOnFailure) {
                    $this->runs->fail($runId, $error->getMessage());

                    throw $error;
                }

                $failures[] = [
                    'work_id' => $work->id,
                    'error' => $error->getMessage(),
                ];
            }
        }

        $result = $this->result($runId, $verdicts, $failures, $startedAt);
        $this->runs->complete($runId, $result->counts());

        return $result;
    }

    private function screenWorkCommand(ScreenCorpusCommand $command, string $runId, ScreeningWork $work): ScreenWorkCommand
    {
        return new ScreenWorkCommand(
            screeningRunId: $runId,
            projectId: $command->projectId,
            work: $work,
            criteria: $command->criteria,
            stage: $command->stage,
            model: $command->model,
            councilModels: $command->mode === ScreeningRunMode::LLM_COUNCIL
                ? $command->councilModels
                : [],
            mode: $command->mode,
            context: array_merge($command->context, [
                'project_id' => $command->projectId,
                'work_id' => $work->id,
            ]),
            temperature: $command->temperature,
            maxTokens: $command->maxTokens,
            storePrompt: $command->storePrompt,
            storeRawResponse: $command->storeRawResponse,
        );
    }

    /**
     * @param  list<ScreeningVerdict>  $verdicts
     * @param  list<array{work_id: string, error: string}>  $failures
     */
    private function result(string $runId, array $verdicts, array $failures, int|float $startedAt): ScreenCorpusResult
    {
        $included = 0;
        $needsReview = 0;
        $excluded = 0;

        foreach ($verdicts as $verdict) {
            match ($verdict->decision) {
                ScreeningDecision::INCLUDE => $included++,
                ScreeningDecision::NEEDS_REVIEW => $needsReview++,
                ScreeningDecision::EXCLUDE => $excluded++,
            };
        }

        return new ScreenCorpusResult(
            runId: $runId,
            total: count($verdicts) + count($failures),
            included: $included,
            needsReview: $needsReview,
            excluded: $excluded,
            failed: count($failures),
            durationMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
            verdicts: $verdicts,
            failures: $failures,
        );
    }
}
