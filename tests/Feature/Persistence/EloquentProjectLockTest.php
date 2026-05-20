<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Nexus\Shared\Exception\ProjectNotFoundException;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\ValueObject\ProjectLockAction;

it('locks projects with audit metadata', function (): void {
    createLockTestProject('project-lock-1');

    $locks = app(ProjectLockLifecyclePort::class);
    $state = $locks->lock(
        projectId: 'project-lock-1',
        actorId: 'admin-1',
        reason: 'ready for screening',
        metadata: ['source' => 'feature-test'],
    );

    expect($state->projectId)->toBe('project-lock-1')
        ->and($state->isLocked)->toBeTrue()
        ->and($state->status)->toBe('locked')
        ->and($state->lockedAt)->not->toBeNull()
        ->and($state->lockedBy)->toBe('admin-1')
        ->and($state->lockReason)->toBe('ready for screening')
        ->and(app(ProjectLockPort::class)->isLocked('project-lock-1'))->toBeTrue();

    $this->assertDatabaseHas('projects', [
        'id' => 'project-lock-1',
        'status' => 'locked',
        'locked_by' => 'admin-1',
        'lock_reason' => 'ready for screening',
        'unlocked_at' => null,
    ]);
    $this->assertDatabaseHas('project_lock_audits', [
        'project_id' => 'project-lock-1',
        'action' => ProjectLockAction::LOCKED->value,
        'actor_id' => 'admin-1',
        'reason' => 'ready for screening',
    ]);

    $audit = DB::table('project_lock_audits')
        ->where('project_id', 'project-lock-1')
        ->where('action', ProjectLockAction::LOCKED->value)
        ->first();

    expect(json_decode($audit->metadata, true))->toBe(['source' => 'feature-test']);
});

it('unlocks projects with audit metadata', function (): void {
    createLockTestProject('project-lock-2');

    $locks = app(ProjectLockLifecyclePort::class);
    $locks->lock('project-lock-2', actorId: 'admin-1', reason: 'initial lock');
    $state = $locks->unlock(
        projectId: 'project-lock-2',
        actorId: 'admin-2',
        reason: 'add late provider results',
        metadata: ['ticket' => 'NS-10'],
    );

    expect($state->projectId)->toBe('project-lock-2')
        ->and($state->isLocked)->toBeFalse()
        ->and($state->status)->toBe('draft')
        ->and($state->lockedAt)->toBeNull()
        ->and($state->lockedBy)->toBe('admin-1')
        ->and($state->lockReason)->toBe('initial lock')
        ->and($state->unlockedAt)->not->toBeNull()
        ->and($state->unlockedBy)->toBe('admin-2')
        ->and($state->unlockReason)->toBe('add late provider results')
        ->and(app(ProjectLockPort::class)->isLocked('project-lock-2'))->toBeFalse();

    $this->assertDatabaseHas('projects', [
        'id' => 'project-lock-2',
        'status' => 'draft',
        'locked_at' => null,
        'unlocked_by' => 'admin-2',
        'unlock_reason' => 'add late provider results',
    ]);
    $this->assertDatabaseHas('project_lock_audits', [
        'project_id' => 'project-lock-2',
        'action' => ProjectLockAction::UNLOCKED->value,
        'actor_id' => 'admin-2',
        'reason' => 'add late provider results',
    ]);

    $audit = DB::table('project_lock_audits')
        ->where('project_id', 'project-lock-2')
        ->where('action', ProjectLockAction::UNLOCKED->value)
        ->first();

    expect(json_decode($audit->metadata, true))->toBe(['ticket' => 'NS-10']);
});

it('rejects lifecycle operations for missing projects', function (): void {
    $locks = app(ProjectLockLifecyclePort::class);

    expect(fn () => $locks->status('missing-project'))
        ->toThrow(ProjectNotFoundException::class, 'Project missing-project was not found.');
    expect(fn () => $locks->lock('missing-project'))
        ->toThrow(ProjectNotFoundException::class, 'Project missing-project was not found.');
    expect(fn () => $locks->unlock('missing-project'))
        ->toThrow(ProjectNotFoundException::class, 'Project missing-project was not found.');
});

function createLockTestProject(string $projectId): void
{
    DB::table('projects')->insert([
        'id' => $projectId,
        'name' => "Project {$projectId}",
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
