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

    $this->recorder->record(JobLifecycleRecord::progressed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        progressKey: 'provider:openalex',
        context: ['project_id' => 'project-a', 'provider_alias' => 'openalex'],
        summary: ['result_count' => 10],
        durationMs: 30,
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
    $this->assertDatabaseHas('job_lifecycle_records', [
        'run_id' => 'run-123',
        'job_name' => 'search',
        'status' => JobLifecycleStatus::PROGRESSED->value,
        'project_id' => 'project-a',
        'duration_ms' => 30,
    ]);

    $completed = DB::table('job_lifecycle_records')
        ->where('run_id', 'run-123')
        ->where('status', JobLifecycleStatus::COMPLETED->value)
        ->first();
    $progressed = DB::table('job_lifecycle_records')
        ->where('run_id', 'run-123')
        ->where('status', JobLifecycleStatus::PROGRESSED->value)
        ->first();

    expect(json_decode($completed->context, true))->toBe(['project_id' => 'project-a'])
        ->and(json_decode($completed->summary, true))->toBe(['success_count' => 2, 'failure_count' => 0])
        ->and(json_decode($progressed->context, true))->toBe([
            'project_id' => 'project-a',
            'provider_alias' => 'openalex',
            'progress_key' => 'provider:openalex',
        ])
        ->and(json_decode($progressed->summary, true))->toBe(['result_count' => 10]);
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

it('upserts progress records by run and progress key', function (): void {
    $first = JobLifecycleRecord::progressed(
        runId: 'run-789',
        jobName: 'snowball_corpus',
        jobClass: \Nexus\Laravel\Job\SnowballJob::class,
        progressKey: 'round:1',
        context: ['project_id' => 'project-a', 'depth' => 1],
        summary: ['net_new_count' => 3],
        durationMs: 10,
    );
    $retry = JobLifecycleRecord::progressed(
        runId: 'run-789',
        jobName: 'snowball_corpus',
        jobClass: \Nexus\Laravel\Job\SnowballJob::class,
        progressKey: 'round:1',
        context: ['project_id' => 'project-a', 'depth' => 1],
        summary: ['net_new_count' => 4],
        durationMs: 15,
    );

    $this->recorder->record($first);
    $this->recorder->record($retry);

    $this->assertDatabaseCount('job_lifecycle_records', 1);
    $this->assertDatabaseHas('job_lifecycle_records', [
        'idempotency_key' => $first->idempotencyKey,
        'run_id' => 'run-789',
        'job_name' => 'snowball_corpus',
        'status' => JobLifecycleStatus::PROGRESSED->value,
        'project_id' => 'project-a',
        'duration_ms' => 15,
    ]);

    $row = DB::table('job_lifecycle_records')
        ->where('run_id', 'run-789')
        ->where('status', JobLifecycleStatus::PROGRESSED->value)
        ->first();

    expect(json_decode($row->summary, true))->toBe(['net_new_count' => 4]);
});
