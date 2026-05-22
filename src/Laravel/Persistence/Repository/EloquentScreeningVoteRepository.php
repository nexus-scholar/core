<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Nexus\Laravel\Model\ScreeningVoteModel;
use Nexus\Screening\Application\Port\ScreeningVoteRepositoryPort;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVote;

final class EloquentScreeningVoteRepository implements ScreeningVoteRepositoryPort
{
    public function record(ScreeningVote $vote): void
    {
        ScreeningVoteModel::query()->updateOrCreate(
            ['id' => $vote->id],
            [
                'screening_run_id' => $vote->screeningRunId,
                'screening_decision_id' => $vote->screeningDecisionId,
                'project_id' => $vote->projectId,
                'work_id' => $vote->workId,
                'stage' => $vote->stage->value,
                'provider' => $vote->provider,
                'model' => $vote->model,
                'attempt' => $vote->attempt,
                'decision' => $vote->decision?->value,
                'confidence' => $vote->confidence,
                'reason' => $vote->rationale->reason,
                'evidence' => $vote->rationale->evidence,
                'uncertainty' => $vote->rationale->uncertainty,
                'exclusion_basis' => $vote->rationale->exclusionBasis,
                'prompt_hash' => $vote->promptHash,
                'response_hash' => $vote->responseHash,
                'prompt' => $vote->prompt,
                'raw_response' => $vote->rawResponse,
                'usage' => $vote->usage,
                'latency_ms' => $vote->latencyMs,
                'error' => $vote->error,
            ],
        );
    }

    public function forDecision(string $screeningDecisionId): array
    {
        return ScreeningVoteModel::query()
            ->where('screening_decision_id', $screeningDecisionId)
            ->orderBy('model')
            ->orderBy('attempt')
            ->get()
            ->map(fn (ScreeningVoteModel $model): ScreeningVote => $this->toDomain($model))
            ->all();
    }

    private function toDomain(ScreeningVoteModel $model): ScreeningVote
    {
        $decision = $model->decision === null ? null : ScreeningDecision::from((string) $model->decision);

        if ($decision === null) {
            return ScreeningVote::failed(
                id: (string) $model->id,
                screeningRunId: (string) $model->screening_run_id,
                screeningDecisionId: $model->screening_decision_id === null ? null : (string) $model->screening_decision_id,
                projectId: (string) $model->project_id,
                workId: (string) $model->work_id,
                stage: ScreeningStage::from((string) $model->stage),
                provider: (string) $model->provider,
                model: (string) $model->model,
                attempt: (int) $model->attempt,
                error: (string) ($model->error ?? 'unknown error'),
                latencyMs: $model->latency_ms === null ? null : (int) $model->latency_ms,
            );
        }

        return ScreeningVote::model(
            id: (string) $model->id,
            screeningRunId: (string) $model->screening_run_id,
            screeningDecisionId: $model->screening_decision_id === null ? null : (string) $model->screening_decision_id,
            projectId: (string) $model->project_id,
            workId: (string) $model->work_id,
            stage: ScreeningStage::from((string) $model->stage),
            provider: (string) $model->provider,
            model: (string) $model->model,
            attempt: (int) $model->attempt,
            decision: $decision,
            confidence: (float) $model->confidence,
            rationale: new ScreeningRationale(
                reason: (string) ($model->reason ?? ''),
                evidence: array_values($model->evidence ?? []),
                uncertainty: array_values($model->uncertainty ?? []),
                exclusionBasis: array_values($model->exclusion_basis ?? []),
            ),
            usage: $model->usage ?? [],
            latencyMs: $model->latency_ms === null ? null : (int) $model->latency_ms,
            promptHash: $model->prompt_hash === null ? null : (string) $model->prompt_hash,
            responseHash: $model->response_hash === null ? null : (string) $model->response_hash,
            prompt: $model->prompt === null ? null : (string) $model->prompt,
            rawResponse: $model->raw_response === null ? null : (string) $model->raw_response,
        );
    }
}
