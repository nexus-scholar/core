<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain\Port;

use Nexus\Deduplication\Domain\DedupCluster;

interface ClusterRepositoryPort
{
    public function findById(string $clusterId): ?DedupCluster;

    /**
     * @return DedupCluster[]
     */
    public function findByProject(string $projectId): array;

    public function save(DedupCluster $cluster): void;
}
