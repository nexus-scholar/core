<?php

declare(strict_types=1);

namespace Nexus\Shared\Port;

use Nexus\Shared\ValueObject\JobLifecycleRecord;

interface JobLifecycleRecorderPort
{
    /**
     * Implementations should upsert by $record->idempotencyKey.
     */
    public function record(JobLifecycleRecord $record): void;
}
