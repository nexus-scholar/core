<?php

declare(strict_types=1);

use Nexus\Deduplication\Application\LockCorpus;
use Nexus\Deduplication\Application\LockCorpusHandler;
use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\DedupClusterId;
use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\TransactionPort;
use Nexus\Shared\ValueObject\ProjectLockState;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('locks the project and every unlocked cluster inside a transaction', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1000/lock')]),
        title: 'Lock Test',
        sourceProvider: 'test'
    );

    $cluster = DedupCluster::reconstitute(
        id: DedupClusterId::generate(),
        projectId: 'project-1',
        representative: $work,
        members: [$work],
        isLocked: false,
    );

    $repository = new class($cluster) implements ClusterRepositoryPort
    {
        /** @var DedupCluster[] */
        public array $saved = [];

        public function __construct(private DedupCluster $cluster) {}

        public function findById(string $clusterId): ?DedupCluster
        {
            return null;
        }

        public function findByProject(string $projectId): array
        {
            return $projectId === 'project-1' ? [$this->cluster] : [];
        }

        public function save(DedupCluster $cluster): void
        {
            $this->saved[] = $cluster;
        }
    };

    $transactions = new class implements TransactionPort
    {
        public bool $ran = false;

        public function run(callable $callback): mixed
        {
            $this->ran = true;

            return $callback();
        }
    };

    $locks = new class implements ProjectLockLifecyclePort
    {
        public ?string $lockedProjectId = null;

        public ?string $actorId = null;

        public ?string $reason = null;

        /** @var array<string, mixed> */
        public array $metadata = [];

        public function lock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState
        {
            $this->lockedProjectId = $projectId;
            $this->actorId = $actorId;
            $this->reason = $reason;
            $this->metadata = $metadata;

            return new ProjectLockState($projectId, true, 'locked');
        }

        public function unlock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState
        {
            return new ProjectLockState($projectId, false, 'draft');
        }

        public function status(string $projectId): ProjectLockState
        {
            return new ProjectLockState($projectId, true, 'locked');
        }
    };

    $handler = new LockCorpusHandler($repository, $locks, $transactions);
    $handler->handle(new LockCorpus(
        projectId: 'project-1',
        actorId: 'admin-1',
        reason: 'ready for screening',
        metadata: ['source' => 'test'],
    ));

    expect($transactions->ran)->toBeTrue()
        ->and($locks->lockedProjectId)->toBe('project-1')
        ->and($locks->actorId)->toBe('admin-1')
        ->and($locks->reason)->toBe('ready for screening')
        ->and($locks->metadata)->toBe(['source' => 'test'])
        ->and($cluster->isLocked)->toBeTrue()
        ->and($repository->saved)->toHaveCount(1);
});
