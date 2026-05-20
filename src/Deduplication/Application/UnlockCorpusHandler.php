<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\TransactionPort;

final class UnlockCorpusHandler
{
    public function __construct(
        private readonly ClusterRepositoryPort $clusterRepository,
        private readonly ProjectLockLifecyclePort $projectLocks,
        private readonly TransactionPort $transactions,
    ) {}

    public function handle(UnlockCorpus $command): void
    {
        $this->transactions->run(function () use ($command): void {
            $this->projectLocks->unlock(
                projectId: $command->projectId,
                actorId: $command->actorId,
                reason: $command->reason,
                metadata: $command->metadata,
            );

            $clusters = $this->clusterRepository->findByProject($command->projectId);

            foreach ($clusters as $cluster) {
                if ($cluster->isLocked) {
                    $cluster->isLocked = false;
                    $this->clusterRepository->save($cluster);
                }
            }
        });
    }
}
