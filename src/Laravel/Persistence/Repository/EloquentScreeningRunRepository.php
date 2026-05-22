<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Nexus\Laravel\Model\ScreeningRunModel;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningRunStatus;
use Nexus\Screening\Domain\ScreeningStage;

final class EloquentScreeningRunRepository implements ScreeningRunRepositoryPort
{
    public function get(string $screeningRunId): ?ScreeningRun
    {
        $model = ScreeningRunModel::query()->find($screeningRunId);

        return $model instanceof ScreeningRunModel ? $this->toDomain($model) : null;
    }

    public function start(ScreeningRun $run): void
    {
        ScreeningRunModel::query()->updateOrCreate(
            ['id' => $run->id],
            [
                'project_id' => $run->projectId,
                'stage' => $run->stage->value,
                'name' => $run->name,
                'mode' => $run->mode->value,
                'status' => $run->status->value,
                'criteria_hash' => $run->resolvedCriteriaHash(),
                'criteria' => $run->criteria->toArray(),
                'config' => $run->config,
                'source' => $run->source,
                'counts' => $run->counts,
                'started_at' => $run->startedAt,
                'completed_at' => $run->completedAt,
            ],
        );
    }

    public function complete(string $screeningRunId, array $counts): void
    {
        ScreeningRunModel::query()
            ->whereKey($screeningRunId)
            ->update([
                'status' => ScreeningRunStatus::COMPLETED->value,
                'counts' => $counts,
                'completed_at' => now(),
            ]);
    }

    public function fail(string $screeningRunId, string $message): void
    {
        ScreeningRunModel::query()
            ->whereKey($screeningRunId)
            ->update([
                'status' => ScreeningRunStatus::FAILED->value,
                'counts' => ['error' => $message],
                'completed_at' => now(),
            ]);
    }

    private function toDomain(ScreeningRunModel $model): ScreeningRun
    {
        return new ScreeningRun(
            id: (string) $model->id,
            projectId: (string) $model->project_id,
            stage: ScreeningStage::from((string) $model->stage),
            mode: ScreeningRunMode::from((string) $model->mode),
            status: ScreeningRunStatus::from((string) $model->status),
            criteria: ScreeningCriteria::fromArray($model->criteria ?? []),
            name: $model->name === null ? null : (string) $model->name,
            config: $model->config ?? [],
            source: $model->source ?? [],
            counts: $model->counts ?? [],
            startedAt: $model->started_at?->toDateTimeImmutable(),
            completedAt: $model->completed_at?->toDateTimeImmutable(),
            criteriaHash: $model->criteria_hash === null ? null : (string) $model->criteria_hash,
        );
    }
}
