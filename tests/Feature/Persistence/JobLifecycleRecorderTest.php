<?php

declare(strict_types=1);

use Nexus\Laravel\Job\SearchJob;
use Nexus\Laravel\Persistence\EloquentJobLifecycleRecorder;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;
use Nexus\Shared\ValueObject\JobLifecycleStatus;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->recorder = app(JobLifecycleRecorderPort::class);
});

it('persists job lifecycle records through the configured recorder port', function (): void {
    expect($this->recorder)->toBeInstanceOf(EloquentJobLifecycleRecorder::class);

    $this->recorder->record(JobLifecycleRecord::started(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a', 'query_count' => 2],
    ));

    $this->recorder->record(JobLifecycleRecord::completed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
        summary: ['success_count' => 2, 'failure_count' => 0],
        durationMs: 42,
    ));

    $this->assertDatabaseHas('job_lifecycle_records', [
        'run_id' => 'run-123',
        'job_name' => 'search',
        'job_class' => SearchJob::class,
        'status' => JobLifecycleStatus::STARTED->value,
        'project_id' => 'project-a',
        'duration_ms' => 0,
    ]);
    $this->assertDatabaseHas('job_lifecycle_records', [
        'run_id' => 'run-123',
        'job_name' => 'search',
        'status' => JobLifecycleStatus::COMPLETED->value,
        'duration_ms' => 42,
    ]);

    $completed = DB::table('job_lifecycle_records')
        ->where('run_id', 'run-123')
        ->where('status', JobLifecycleStatus::COMPLETED->value)
        ->first();

    expect(json_decode($completed->context, true))->toBe(['project_id' => 'project-a'])
        ->and(json_decode($completed->summary, true))->toBe(['success_count' => 2, 'failure_count' => 0]);
});

it('upserts duplicate lifecycle records by idempotency key', function (): void {
    $first = JobLifecycleRecord::failed(
        runId: 'run-456',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
        errorClass: RuntimeException::class,
        errorMessage: 'first failure',
        durationMs: 10,
    );
    $retry = JobLifecycleRecord::failed(
        runId: 'run-456',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a', 'attempt' => 2],
        errorClass: RuntimeException::class,
        errorMessage: 'retry failure',
        durationMs: 25,
    );

    $this->recorder->record($first);
    $this->recorder->record($retry);

    $this->assertDatabaseCount('job_lifecycle_records', 1);
    $this->assertDatabaseHas('job_lifecycle_records', [
        'idempotency_key' => $first->idempotencyKey,
        'run_id' => 'run-456',
        'status' => JobLifecycleStatus::FAILED->value,
        'project_id' => 'project-a',
        'error_message' => 'retry failure',
        'duration_ms' => 25,
    ]);
});
