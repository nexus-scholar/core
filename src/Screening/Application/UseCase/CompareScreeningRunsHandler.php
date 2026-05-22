<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningVerdict;

final readonly class CompareScreeningRunsHandler
{
    public function __construct(
        private ScreeningRunRepositoryPort $runs,
        private ScreeningDecisionRepositoryPort $decisions,
    ) {}

    public function handle(CompareScreeningRunsCommand $command): ScreeningRunComparisonResult
    {
        $baselineRun = $this->requiredRun($command->baselineRunId);
        $candidateRun = $this->requiredRun($command->candidateRunId);
        $this->assertComparable($command, $baselineRun, $candidateRun);

        $baseline = $this->byWorkId($this->decisions->forRun($baselineRun->id), $command);
        $candidate = $this->byWorkId($this->decisions->forRun($candidateRun->id), $command);
        $workIds = array_values(array_unique([...array_keys($baseline), ...array_keys($candidate)]));
        sort($workIds);

        $agreement = 0;
        $disagreement = 0;
        $transitions = [];
        $missingInBaseline = [];
        $missingInCandidate = [];
        $rows = [];

        foreach ($workIds as $workId) {
            $baselineVerdict = $baseline[$workId] ?? null;
            $candidateVerdict = $candidate[$workId] ?? null;

            if ($baselineVerdict === null) {
                $missingInBaseline[] = $workId;
            }

            if ($candidateVerdict === null) {
                $missingInCandidate[] = $workId;
            }

            if ($baselineVerdict !== null && $candidateVerdict !== null) {
                $from = $baselineVerdict->decision->value;
                $to = $candidateVerdict->decision->value;
                $transitions[$from][$to] = ($transitions[$from][$to] ?? 0) + 1;

                if ($from === $to) {
                    $agreement++;
                } else {
                    $disagreement++;
                }
            }

            if ($command->includeRows) {
                $rows[] = new ScreeningRunComparisonRow(
                    workId: $workId,
                    baselineDecision: $baselineVerdict?->decision->value,
                    candidateDecision: $candidateVerdict?->decision->value,
                    changed: $baselineVerdict?->decision !== $candidateVerdict?->decision,
                    baseline: $baselineVerdict === null ? [] : $this->verdictSummary($baselineVerdict),
                    candidate: $candidateVerdict === null ? [] : $this->verdictSummary($candidateVerdict),
                );
            }
        }

        $comparable = $agreement + $disagreement;

        return new ScreeningRunComparisonResult(
            projectId: $command->projectId,
            baselineRun: $this->runSummary($baselineRun),
            candidateRun: $this->runSummary($candidateRun),
            comparableTotal: $comparable,
            agreementCount: $agreement,
            disagreementCount: $disagreement,
            agreementRate: $comparable === 0 ? 0.0 : $agreement / $comparable,
            disagreementRate: $comparable === 0 ? 0.0 : $disagreement / $comparable,
            transitionCounts: $transitions,
            missingInBaseline: $missingInBaseline,
            missingInCandidate: $missingInCandidate,
            rows: $rows,
            referenceRunId: $this->referenceRunId($baselineRun, $candidateRun),
        );
    }

    private function requiredRun(string $runId): ScreeningRun
    {
        return $this->runs->get($runId)
            ?? throw new \InvalidArgumentException("Screening run {$runId} was not found.");
    }

    private function assertComparable(
        CompareScreeningRunsCommand $command,
        ScreeningRun $baselineRun,
        ScreeningRun $candidateRun,
    ): void {
        if ($baselineRun->projectId !== $command->projectId || $candidateRun->projectId !== $command->projectId) {
            throw new \InvalidArgumentException('Screening runs must belong to the requested project.');
        }

        if ($command->stage !== null
            && ($baselineRun->stage !== $command->stage || $candidateRun->stage !== $command->stage)) {
            throw new \InvalidArgumentException('Screening runs must match the requested stage.');
        }
    }

    /**
     * @param  list<ScreeningVerdict>  $verdicts
     * @return array<string, ScreeningVerdict>
     */
    private function byWorkId(array $verdicts, CompareScreeningRunsCommand $command): array
    {
        $indexed = [];

        foreach ($verdicts as $verdict) {
            if ($command->stage !== null && $verdict->stage !== $command->stage) {
                continue;
            }

            $indexed[$verdict->workId] ??= $verdict;
        }

        return $indexed;
    }

    /**
     * @return array<string, mixed>
     */
    private function runSummary(ScreeningRun $run): array
    {
        return [
            'id' => $run->id,
            'project_id' => $run->projectId,
            'stage' => $run->stage->value,
            'mode' => $run->mode->value,
            'status' => $run->status->value,
            'name' => $run->name,
            'criteria_hash' => $run->resolvedCriteriaHash(),
            'counts' => $run->counts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verdictSummary(ScreeningVerdict $verdict): array
    {
        return [
            'decision_id' => $verdict->id,
            'decision' => $verdict->decision->value,
            'confidence' => $verdict->confidence,
            'source' => $verdict->source,
            'decided_by' => $verdict->decidedBy,
            'reason' => $verdict->rationale->reason,
            'evidence' => $verdict->rationale->evidence,
            'uncertainty' => $verdict->rationale->uncertainty,
            'exclusion_basis' => $verdict->rationale->exclusionBasis,
        ];
    }

    private function referenceRunId(ScreeningRun $baselineRun, ScreeningRun $candidateRun): ?string
    {
        if ($baselineRun->mode === ScreeningRunMode::HUMAN) {
            return $baselineRun->id;
        }

        return $candidateRun->mode === ScreeningRunMode::HUMAN ? $candidateRun->id : null;
    }
}
