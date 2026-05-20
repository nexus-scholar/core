<?php

declare(strict_types=1);

namespace Nexus\Shared\Port;

use Nexus\Shared\ValueObject\ProjectLockState;

interface ProjectLockLifecyclePort
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function lock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState;

    /**
     * @param array<string, mixed> $metadata
     */
    public function unlock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState;

    public function status(string $projectId): ProjectLockState;
}
