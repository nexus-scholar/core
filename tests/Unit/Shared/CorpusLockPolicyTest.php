<?php

declare(strict_types=1);

use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Exception\ProjectNotLockedException;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\ValueObject\CorpusOperation;
use Nexus\Shared\ValueObject\CorpusSnapshot;
use Nexus\Shared\ValueObject\ProjectLockState;

it('blocks corpus mutation after a project is locked', function (): void {
    $policy = new CorpusLockPolicy(
        new CorpusPolicyTestLocks(['project-1' => true]),
        new CorpusPolicyTestMembership,
    );

    expect(fn () => $policy->assertCorpusMutable('project-1', CorpusOperation::SNOWBALL))
        ->toThrow(ProjectLockedException::class, 'snowball cannot mutate the corpus');
});

it('requires a locked corpus for screening and adjudication', function (): void {
    $policy = new CorpusLockPolicy(
        new CorpusPolicyTestLocks(['project-1' => false]),
        new CorpusPolicyTestMembership,
    );

    expect(fn () => $policy->assertCorpusLocked('project-1', CorpusOperation::SCREEN))
        ->toThrow(ProjectNotLockedException::class, 'must be locked before screen');
});

it('rejects works outside a project corpus', function (): void {
    $policy = new CorpusLockPolicy(
        new CorpusPolicyTestLocks(['project-1' => true]),
        new CorpusPolicyTestMembership(['missing-work']),
    );

    expect(fn () => $policy->assertWorksBelongToProject(
        'project-1',
        ['work-1', 'missing-work'],
        CorpusOperation::ADJUDICATE,
    ))->toThrow(InvalidArgumentException::class, 'missing-work');
});

it('adds citable export metadata from lock state without blocking export', function (): void {
    $policy = new CorpusLockPolicy(
        new CorpusPolicyTestLocks(['project-1' => true]),
        new CorpusPolicyTestMembership,
        new CorpusPolicyTestLifecycle(new ProjectLockState(
            projectId: 'project-1',
            isLocked: true,
            status: 'locked',
            lockedAt: new DateTimeImmutable('2026-05-22T12:00:00+00:00'),
        )),
        new CorpusPolicyTestSnapshots(new CorpusSnapshot(
            id: 'snapshot-1',
            projectId: 'project-1',
            lockedAt: new DateTimeImmutable('2026-05-22T12:00:00+00:00'),
            workCount: 2,
        )),
    );

    expect($policy->exportMetadata('project-1'))->toMatchArray([
        'project_locked' => true,
        'locked_at' => '2026-05-22T12:00:00+00:00',
        'lock_status' => 'locked',
        'corpus_snapshot_id' => 'snapshot-1',
        'snapshot_work_count' => 2,
        'citable' => true,
        'final' => true,
    ]);
});

it('does not mark locked exports citable without an immutable snapshot', function (): void {
    $policy = new CorpusLockPolicy(
        new CorpusPolicyTestLocks(['project-1' => true]),
        new CorpusPolicyTestMembership,
        new CorpusPolicyTestLifecycle(new ProjectLockState(
            projectId: 'project-1',
            isLocked: true,
            status: 'locked',
            lockedAt: new DateTimeImmutable('2026-05-22T12:00:00+00:00'),
        )),
        new CorpusPolicyTestSnapshots,
    );

    expect($policy->exportMetadata('project-1'))->toMatchArray([
        'project_locked' => true,
        'locked_at' => '2026-05-22T12:00:00+00:00',
        'corpus_snapshot_id' => null,
        'citable' => false,
        'final' => false,
    ]);
});

final class CorpusPolicyTestLocks implements ProjectLockPort
{
    /**
     * @param  array<string, bool>  $locks
     */
    public function __construct(private readonly array $locks) {}

    public function isLocked(string $projectId): bool
    {
        return $this->locks[$projectId] ?? false;
    }
}

final class CorpusPolicyTestMembership implements ProjectWorkMembershipPort
{
    /**
     * @param  list<string>  $missing
     */
    public function __construct(private readonly array $missing = []) {}

    public function missingWorkIds(string $projectId, array $workIds): array
    {
        return array_values(array_intersect($workIds, $this->missing));
    }
}

final class CorpusPolicyTestLifecycle implements ProjectLockLifecyclePort
{
    public function __construct(private readonly ProjectLockState $state) {}

    public function lock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState
    {
        return $this->state;
    }

    public function unlock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState
    {
        return $this->state;
    }

    public function status(string $projectId): ProjectLockState
    {
        return $this->state;
    }
}

final class CorpusPolicyTestSnapshots implements CorpusSnapshotRepositoryPort
{
    public function __construct(private readonly ?CorpusSnapshot $snapshot = null) {}

    public function createForLockedProject(
        string $projectId,
        DateTimeImmutable $lockedAt,
        ?string $actorId = null,
        ?string $reason = null,
        array $metadata = [],
    ): CorpusSnapshot {
        return $this->snapshot ?? new CorpusSnapshot(
            id: 'created-snapshot',
            projectId: $projectId,
            lockedAt: $lockedAt,
            workCount: 0,
            createdBy: $actorId,
            lockReason: $reason,
            metadata: $metadata,
        );
    }

    public function latestForProject(string $projectId): ?CorpusSnapshot
    {
        return $this->snapshot?->projectId === $projectId ? $this->snapshot : null;
    }
}
