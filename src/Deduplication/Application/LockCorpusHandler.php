<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\Shared\Port\TransactionPort;

final class LockCorpusHandler
{
    public function __construct(
        private readonly ClusterRepositoryPort $clusterRepository,
        private readonly TransactionPort $transactions,
    ) {}

    public function handle(LockCorpus $command): void
    {
        $this->transactions->run(function () use ($command): void {
            $clusters = $this->clusterRepository->findByProject($command->projectId);
            
            foreach ($clusters as $cluster) {
                if (!$cluster->isLocked) {
                    $cluster->isLocked = true;
                    $this->clusterRepository->save($cluster);
                }
            }
        });
    }
}
