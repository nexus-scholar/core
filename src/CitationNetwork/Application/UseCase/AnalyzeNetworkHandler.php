<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Application\Exception\CitationGraphNotFound;
use Nexus\CitationNetwork\Domain\NetworkMetrics;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Nexus\CitationNetwork\Domain\Port\GraphAlgorithmPort;

final readonly class AnalyzeNetworkHandler
{
    public function __construct(
        private CitationGraphRepositoryPort $repository,
        private GraphAlgorithmPort $algorithms,
    ) {
    }

    public function handle(AnalyzeNetwork $command): NetworkMetrics
    {
        $graph = $this->repository->findById($command->graphId);

        if ($graph === null) {
            throw new CitationGraphNotFound($command->graphId);
        }

        $metrics = $this->algorithms->compute($graph);

        if ($command->persistMetrics) {
            $this->repository->saveMetrics($command->graphId, $metrics);
        }

        return $metrics;
    }
}
