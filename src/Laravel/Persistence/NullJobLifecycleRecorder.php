<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;

final class NullJobLifecycleRecorder implements JobLifecycleRecorderPort
{
    public function record(JobLifecycleRecord $record): void
    {
        // Host applications can bind JobLifecycleRecorderPort to persist progress.
    }
}
