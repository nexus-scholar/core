<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain\Port;

use Nexus\Deduplication\Domain\DedupCluster;

interface ClusterRepositoryPort
{
    public function findById(string $clusterId): ?DedupCluster;
    public function save(DedupCluster $cluster): void;
}
