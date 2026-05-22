<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Nexus\Laravel\Model\ScreeningDecisionModel;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;

final class EloquentScreeningDecisionRepository implements ScreeningDecisionRepositoryPort
{
    public function record(ScreeningVerdict $verdict): void
    {
        ScreeningDecisionModel::query()->updateOrCreate(
            ['id' => $verdict->id],
            [
                'screening_run_id' => $verdict->screeningRunId,
                'project_id' => $verdict->projectId,
                'work_id' => $verdict->workId,
                'stage' => $verdict->stage->value,
                'decision' => $verdict->decision->value,
                'decision_source' => $verdict->source,
                'confidence' => $verdict->confidence,
                'included' => $verdict->included(),
                'criteria_hash' => $verdict->criteriaHash,
                'decision_rank' => $this->decisionRank($verdict->decision),
                'reason' => $verdict->rationale->reason,
                'evidence' => $verdict->rationale->evidence,
                'uncertainty' => $verdict->rationale->uncertainty,
                'exclusion_basis' => $verdict->rationale->exclusionBasis,
                'decided_by' => $verdict->decidedBy,
                'decided_at' => $verdict->decidedAt ?? now(),
                'metadata' => [
                    'source' => $verdict->source,
                    'vote_count' => count($verdict->votes),
                ],
            ],
        );
    }

    public function latestForWork(string $projectId, string $workId, ScreeningStage $stage): ?ScreeningVerdict
    {
        $model = ScreeningDecisionModel::query()
            ->where('project_id', $projectId)
            ->where('work_id', $workId)
            ->where('stage', $stage->value)
            ->orderByDesc('decided_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $model instanceof ScreeningDecisionModel) {
            return null;
        }

        return new ScreeningVerdict(
            id: (string) $model->id,
            screeningRunId: $model->screening_run_id === null ? null : (string) $model->screening_run_id,
            projectId: (string) $model->project_id,
            workId: (string) $model->work_id,
            stage: ScreeningStage::from((string) $model->stage),
            decision: ScreeningDecision::from((string) $model->decision),
            confidence: $model->confidence === null ? null : (float) $model->confidence,
            source: (string) ($model->decision_source ?? 'unknown'),
            rationale: new ScreeningRationale(
                reason: (string) ($model->reason ?? ''),
                evidence: array_values($model->evidence ?? []),
                uncertainty: array_values($model->uncertainty ?? []),
                exclusionBasis: array_values($model->exclusion_basis ?? []),
            ),
            decidedBy: $model->decided_by === null ? null : (string) $model->decided_by,
            decidedAt: $model->decided_at?->toDateTimeImmutable(),
            criteriaHash: $model->criteria_hash === null ? null : (string) $model->criteria_hash,
        );
    }

    private function decisionRank(ScreeningDecision $decision): int
    {
        return match ($decision) {
            ScreeningDecision::INCLUDE => 30,
            ScreeningDecision::NEEDS_REVIEW => 20,
            ScreeningDecision::EXCLUDE => 10,
        };
    }
}
