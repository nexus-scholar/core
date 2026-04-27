<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain\Port;

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphId;

interface CitationGraphRepositoryPort
{
    public function save(CitationGraph $graph): void;
    public function findById(CitationGraphId $id): ?CitationGraph;
    /** @return CitationGraph[] */
    public function findByProjectId(string $projectId): array;
    public function delete(CitationGraphId $id): void;
}
