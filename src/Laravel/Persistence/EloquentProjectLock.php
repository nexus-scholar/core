<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Support\Facades\DB;
use Nexus\Shared\Port\ProjectLockPort;

final class EloquentProjectLock implements ProjectLockPort
{
    public function isLocked(string $projectId): bool
    {
        return DB::table('projects')
            ->where('id', $projectId)
            ->whereNotNull('locked_at')
            ->exists();
    }
}
