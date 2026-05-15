<?php

declare(strict_types=1);

use Nexus\Deduplication\Application\LockCorpus;
use Nexus\Deduplication\Application\LockCorpusHandler;
use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\DedupClusterId;
use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\Port\TransactionPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('locks every unlocked cluster inside a transaction', function (): void {
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

    $repository = new class($cluster) implements ClusterRepositoryPort {
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

    $transactions = new class implements TransactionPort {
        public bool $ran = false;

        public function run(callable $callback): mixed
        {
            $this->ran = true;

            return $callback();
        }
    };

    $handler = new LockCorpusHandler($repository, $transactions);
    $handler->handle(new LockCorpus('project-1'));

    expect($transactions->ran)->toBeTrue()
        ->and($cluster->isLocked)->toBeTrue()
        ->and($repository->saved)->toHaveCount(1);
});
