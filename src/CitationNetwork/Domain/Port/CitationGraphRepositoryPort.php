<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain\Port;

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphId;
use Nexus\CitationNetwork\Domain\NetworkMetrics;

interface CitationGraphRepositoryPort
{
    public function save(CitationGraph $graph): void;
    public function findById(CitationGraphId $id): ?CitationGraph;
    /** @return CitationGraph[] */
    public function findByProjectId(string $projectId): array;
    public function saveMetrics(CitationGraphId $id, NetworkMetrics $metrics): void;
    public function delete(CitationGraphId $id): void;
}
