<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Illuminate\Support\Facades\DB;

final class LockCorpusHandler
{
    public function __construct(
        private readonly ClusterRepositoryPort $clusterRepository
    ) {}

    public function handle(LockCorpus $command): void
    {
        DB::transaction(function () use ($command) {
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
