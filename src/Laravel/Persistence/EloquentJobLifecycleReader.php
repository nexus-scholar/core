<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Nexus\Laravel\Model\JobLifecycleRecordModel;
use Nexus\Shared\Port\JobLifecycleReaderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;
use Nexus\Shared\ValueObject\JobLifecycleStatus;

final class EloquentJobLifecycleReader implements JobLifecycleReaderPort
{
    public function forRun(string $runId, int $limit = 100): array
    {
        return JobLifecycleRecordModel::query()
            ->where('run_id', $runId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (JobLifecycleRecordModel $model): JobLifecycleRecord => $this->recordFromModel($model))
            ->all();
    }

    public function latestForProject(string $projectId, int $limit = 25): array
    {
        return JobLifecycleRecordModel::query()
            ->where('project_id', $projectId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (JobLifecycleRecordModel $model): JobLifecycleRecord => $this->recordFromModel($model))
            ->all();
    }

    public function latestStatusForRun(string $runId): ?JobLifecycleStatus
    {
        $status = JobLifecycleRecordModel::query()
            ->where('run_id', $runId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->value('status');

        return $status === null ? null : JobLifecycleStatus::from((string) $status);
    }

    private function recordFromModel(JobLifecycleRecordModel $model): JobLifecycleRecord
    {
        return new JobLifecycleRecord(
            idempotencyKey: (string) $model->idempotency_key,
            runId: (string) $model->run_id,
            jobName: (string) $model->job_name,
            jobClass: (string) $model->job_class,
            status: JobLifecycleStatus::from((string) $model->status),
            context: is_array($model->context) ? $model->context : [],
            summary: is_array($model->summary) ? $model->summary : [],
            errorClass: $model->error_class === null ? null : (string) $model->error_class,
            errorMessage: $model->error_message === null ? null : (string) $model->error_message,
            durationMs: (int) $model->duration_ms,
            occurredAt: $this->dateTime($model->occurred_at),
        );
    }

    private function dateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
