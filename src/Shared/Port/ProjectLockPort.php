<?php

declare(strict_types=1);

namespace Nexus\Shared\Port;

interface ProjectLockPort
{
    public function isLocked(string $projectId): bool;
}
