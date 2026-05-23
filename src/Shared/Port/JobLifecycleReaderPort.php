<?php

declare(strict_types=1);

namespace Nexus\Shared\Port;

use Nexus\Shared\ValueObject\JobLifecycleRecord;
use Nexus\Shared\ValueObject\JobLifecycleStatus;

interface JobLifecycleReaderPort
{
    /**
     * @return list<JobLifecycleRecord>
     */
    public function forRun(string $runId, int $limit = 100): array;

    /**
     * @return list<JobLifecycleRecord>
     */
    public function latestForProject(string $projectId, int $limit = 25): array;

    public function latestStatusForRun(string $runId): ?JobLifecycleStatus;
}
