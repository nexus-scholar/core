<?php

declare(strict_types=1);

namespace Nexus\Laravel\Listener;

use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobProgressed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;

final readonly class RecordNexusJobLifecycle
{
    public function __construct(
        private JobLifecycleRecorderPort $recorder,
    ) {}

    public function handle(NexusJobStarted|NexusJobProgressed|NexusJobCompleted|NexusJobFailed $event): void
    {
        $this->recorder->record(match (true) {
            $event instanceof NexusJobStarted => JobLifecycleRecord::started(
                runId: $event->runId,
                jobName: $event->jobName,
                jobClass: $event->jobClass,
                context: $event->context,
                occurredAt: $event->occurredAt,
            ),
            $event instanceof NexusJobProgressed => JobLifecycleRecord::progressed(
                runId: $event->runId,
                jobName: $event->jobName,
                jobClass: $event->jobClass,
                progressKey: $event->progressKey,
                context: $event->context,
                summary: $event->summary,
                durationMs: $event->durationMs,
                occurredAt: $event->occurredAt,
            ),
            $event instanceof NexusJobCompleted => JobLifecycleRecord::completed(
                runId: $event->runId,
                jobName: $event->jobName,
                jobClass: $event->jobClass,
                context: $event->context,
                summary: $event->summary,
                durationMs: $event->durationMs,
                occurredAt: $event->occurredAt,
            ),
            $event instanceof NexusJobFailed => JobLifecycleRecord::failed(
                runId: $event->runId,
                jobName: $event->jobName,
                jobClass: $event->jobClass,
                context: $event->context,
                errorClass: $event->errorClass,
                errorMessage: $event->errorMessage,
                durationMs: $event->durationMs,
                occurredAt: $event->occurredAt,
            ),
        });
    }
}
