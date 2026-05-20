<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Support\Str;
use Nexus\Laravel\Model\JobLifecycleRecordModel;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;

final class EloquentJobLifecycleRecorder implements JobLifecycleRecorderPort
{
    public function record(JobLifecycleRecord $record): void
    {
        $model = JobLifecycleRecordModel::query()->firstOrNew([
            'idempotency_key' => $record->idempotencyKey,
        ]);

        if (! $model->exists) {
            $model->id = (string) Str::uuid();
        }

        $model->fill([
            'run_id'        => $record->runId,
            'job_name'      => $record->jobName,
            'job_class'     => $record->jobClass,
            'status'        => $record->status->value,
            'project_id'    => $this->stringContextValue($record, 'project_id'),
            'work_id'       => $this->stringContextValue($record, 'work_id'),
            'context'       => $record->context,
            'summary'       => $record->summary,
            'error_class'   => $record->errorClass,
            'error_message' => $record->errorMessage,
            'duration_ms'   => $record->durationMs,
            'occurred_at'   => $record->occurredAt,
        ]);
        $model->save();
    }

    private function stringContextValue(JobLifecycleRecord $record, string $key): ?string
    {
        $value = $record->context[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
