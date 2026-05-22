<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Shared\Exception\ProjectNotFoundException;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\ValueObject\ProjectLockAction;
use Nexus\Shared\ValueObject\ProjectLockState;

final class EloquentProjectLock implements ProjectLockLifecyclePort, ProjectLockPort
{
    public function __construct(private readonly ?CorpusSnapshotRepositoryPort $snapshots = null) {}

    public function isLocked(string $projectId): bool
    {
        return DB::table('projects')
            ->where('id', $projectId)
            ->whereNotNull('locked_at')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function lock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState
    {
        return DB::transaction(function () use ($projectId, $actorId, $reason, $metadata): ProjectLockState {
            $now = Carbon::now();

            $this->assertProjectExists($projectId);

            DB::table('projects')
                ->where('id', $projectId)
                ->update([
                    'status' => 'locked',
                    'locked_at' => $now,
                    'locked_by' => $actorId,
                    'lock_reason' => $reason,
                    'unlocked_at' => null,
                    'unlocked_by' => null,
                    'unlock_reason' => null,
                    'updated_at' => $now,
                ]);

            $this->recordAudit($projectId, ProjectLockAction::LOCKED, $actorId, $reason, $metadata, $now);

            $this->snapshots?->createForLockedProject(
                projectId: $projectId,
                lockedAt: DateTimeImmutable::createFromInterface($now),
                actorId: $actorId,
                reason: $reason,
                metadata: $metadata,
            );

            return $this->status($projectId);
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function unlock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState
    {
        return DB::transaction(function () use ($projectId, $actorId, $reason, $metadata): ProjectLockState {
            $now = Carbon::now();

            $this->assertProjectExists($projectId);

            DB::table('projects')
                ->where('id', $projectId)
                ->update([
                    'status' => 'draft',
                    'locked_at' => null,
                    'unlocked_at' => $now,
                    'unlocked_by' => $actorId,
                    'unlock_reason' => $reason,
                    'updated_at' => $now,
                ]);

            $this->recordAudit($projectId, ProjectLockAction::UNLOCKED, $actorId, $reason, $metadata, $now);

            return $this->status($projectId);
        });
    }

    public function status(string $projectId): ProjectLockState
    {
        $project = DB::table('projects')
            ->where('id', $projectId)
            ->first();

        if ($project === null) {
            throw new ProjectNotFoundException("Project {$projectId} was not found.");
        }

        return new ProjectLockState(
            projectId: (string) $project->id,
            isLocked: $project->locked_at !== null,
            status: (string) ($project->status ?? 'draft'),
            lockedAt: $this->date($project->locked_at ?? null),
            lockedBy: $project->locked_by ?? null,
            lockReason: $project->lock_reason ?? null,
            unlockedAt: $this->date($project->unlocked_at ?? null),
            unlockedBy: $project->unlocked_by ?? null,
            unlockReason: $project->unlock_reason ?? null,
        );
    }

    private function assertProjectExists(string $projectId): void
    {
        if (! DB::table('projects')->where('id', $projectId)->exists()) {
            throw new ProjectNotFoundException("Project {$projectId} was not found.");
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordAudit(
        string $projectId,
        ProjectLockAction $action,
        ?string $actorId,
        ?string $reason,
        array $metadata,
        Carbon $occurredAt,
    ): void {
        DB::table('project_lock_audits')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'action' => $action->value,
            'actor_id' => $actorId,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
