<?php

declare(strict_types=1);

use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobProgressed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Laravel\Job\SearchJob;

it('defines serializable job lifecycle event payloads', function (): void {
    $started = new NexusJobStarted(
        runId: 'run-1',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
    );

    $completed = new NexusJobCompleted(
        runId: 'run-1',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
        summary: ['success_count' => 1],
        durationMs: 15,
    );

    $progressed = new NexusJobProgressed(
        runId: 'run-1',
        jobName: 'search',
        jobClass: SearchJob::class,
        progressKey: 'provider:openalex',
        context: ['project_id' => 'project-a', 'provider_alias' => 'openalex'],
        summary: ['result_count' => 10],
        durationMs: 12,
    );

    $failed = new NexusJobFailed(
        runId: 'run-2',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-a'],
        errorClass: RuntimeException::class,
        errorMessage: 'boom',
        durationMs: 16,
    );

    $restoredStarted = unserialize(serialize($started));
    $restoredCompleted = unserialize(serialize($completed));
    $restoredProgressed = unserialize(serialize($progressed));
    $restoredFailed = unserialize(serialize($failed));

    expect($restoredStarted)->toBeInstanceOf(NexusJobStarted::class)
        ->and($restoredStarted->runId)->toBe('run-1')
        ->and($restoredStarted->jobName)->toBe('search')
        ->and($restoredStarted->jobClass)->toBe(SearchJob::class)
        ->and($restoredStarted->context)->toBe(['project_id' => 'project-a'])
        ->and($restoredStarted->occurredAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($restoredCompleted)->toBeInstanceOf(NexusJobCompleted::class)
        ->and($restoredCompleted->runId)->toBe('run-1')
        ->and($restoredCompleted->summary)->toBe(['success_count' => 1])
        ->and($restoredCompleted->durationMs)->toBe(15)
        ->and($restoredProgressed)->toBeInstanceOf(NexusJobProgressed::class)
        ->and($restoredProgressed->runId)->toBe('run-1')
        ->and($restoredProgressed->progressKey)->toBe('provider:openalex')
        ->and($restoredProgressed->context)->toBe(['project_id' => 'project-a', 'provider_alias' => 'openalex'])
        ->and($restoredProgressed->summary)->toBe(['result_count' => 10])
        ->and($restoredProgressed->durationMs)->toBe(12)
        ->and($restoredFailed)->toBeInstanceOf(NexusJobFailed::class)
        ->and($restoredFailed->runId)->toBe('run-2')
        ->and($restoredFailed->errorClass)->toBe(RuntimeException::class)
        ->and($restoredFailed->errorMessage)->toBe('boom')
        ->and($restoredFailed->durationMs)->toBe(16);
});
