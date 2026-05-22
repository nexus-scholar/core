<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Nexus\Laravel\Model\ScreeningRunModel;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunStatus;

final class EloquentScreeningRunRepository implements ScreeningRunRepositoryPort
{
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
                'criteria_hash' => $run->criteria->hash(),
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
}
