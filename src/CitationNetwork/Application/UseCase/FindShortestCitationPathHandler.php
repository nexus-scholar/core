<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Application\Exception\CitationGraphNotFound;
use Nexus\CitationNetwork\Domain\CitationPath;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Nexus\CitationNetwork\Domain\Port\GraphAlgorithmPort;

final readonly class FindShortestCitationPathHandler
{
    public function __construct(
        private CitationGraphRepositoryPort $repository,
        private GraphAlgorithmPort $algorithms,
    ) {
    }

    public function handle(FindShortestCitationPath $query): ?CitationPath
    {
        $graph = $this->repository->findById($query->graphId);

        if ($graph === null) {
            throw new CitationGraphNotFound($query->graphId);
        }

        return $this->algorithms->shortestPath($graph, $query->source, $query->target);
    }
}
