<?php

declare(strict_types=1);

use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobProgressed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Laravel\Job\SearchJob;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;
use Nexus\Shared\ValueObject\JobLifecycleStatus;

it('records lifecycle events through the configured recorder port', function (): void {
    $recorder = new class implements JobLifecycleRecorderPort {
        /** @var list<JobLifecycleRecord> */
        public array $records = [];

        public function record(JobLifecycleRecord $record): void
        {
            $this->records[] = $record;
        }
    };

    app()->instance(JobLifecycleRecorderPort::class, $recorder);

    event(new NexusJobStarted(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
    ));
    event(new NexusJobCompleted(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
        summary: ['success_count' => 1],
        durationMs: 25,
    ));
    event(new NexusJobProgressed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        progressKey: 'provider:openalex',
        context: ['project_id' => 'project-a', 'provider_alias' => 'openalex'],
        summary: ['result_count' => 10],
        durationMs: 20,
    ));
    event(new NexusJobFailed(
        runId: 'run-456',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-b'],
        errorClass: RuntimeException::class,
        errorMessage: 'boom',
        durationMs: 30,
    ));

    expect($recorder->records)->toHaveCount(4)
        ->and($recorder->records[0]->status)->toBe(JobLifecycleStatus::STARTED)
        ->and($recorder->records[0]->runId)->toBe('run-123')
        ->and($recorder->records[0]->context)->toBe(['project_id' => 'project-a'])
        ->and($recorder->records[1]->status)->toBe(JobLifecycleStatus::COMPLETED)
        ->and($recorder->records[1]->summary)->toBe(['success_count' => 1])
        ->and($recorder->records[1]->durationMs)->toBe(25)
        ->and($recorder->records[2]->status)->toBe(JobLifecycleStatus::PROGRESSED)
        ->and($recorder->records[2]->context)->toBe([
            'project_id' => 'project-a',
            'provider_alias' => 'openalex',
            'progress_key' => 'provider:openalex',
        ])
        ->and($recorder->records[2]->summary)->toBe(['result_count' => 10])
        ->and($recorder->records[2]->durationMs)->toBe(20)
        ->and($recorder->records[3]->status)->toBe(JobLifecycleStatus::FAILED)
        ->and($recorder->records[3]->errorClass)->toBe(RuntimeException::class)
        ->and($recorder->records[3]->errorMessage)->toBe('boom');
});

it('uses stable idempotency keys for repeated lifecycle records', function (): void {
    $first = JobLifecycleRecord::completed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
        summary: ['success_count' => 1],
        durationMs: 25,
    );

    $retry = JobLifecycleRecord::completed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
        summary: ['success_count' => 1],
        durationMs: 26,
    );

    $differentStatus = JobLifecycleRecord::failed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
        errorClass: RuntimeException::class,
        errorMessage: 'boom',
        durationMs: 27,
    );
    $progress = JobLifecycleRecord::progressed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        progressKey: 'provider:openalex',
        context: ['project_id' => 'project-a'],
        summary: ['result_count' => 10],
        durationMs: 20,
    );
    $sameProgress = JobLifecycleRecord::progressed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        progressKey: 'provider:openalex',
        context: ['project_id' => 'project-a'],
        summary: ['result_count' => 11],
        durationMs: 21,
    );
    $differentProgress = JobLifecycleRecord::progressed(
        runId: 'run-123',
        jobName: 'search',
        jobClass: SearchJob::class,
        progressKey: 'provider:crossref',
        context: ['project_id' => 'project-a'],
        summary: ['result_count' => 3],
        durationMs: 22,
    );

    expect($first->idempotencyKey)->toBe($retry->idempotencyKey)
        ->and($first->idempotencyKey)->not->toBe($differentStatus->idempotencyKey)
        ->and($progress->idempotencyKey)->toBe($sameProgress->idempotencyKey)
        ->and($progress->idempotencyKey)->not->toBe($differentProgress->idempotencyKey);
});
