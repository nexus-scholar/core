<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;

final readonly class BuildCitationGraphHandler
{
    public function __construct(
        private CitationGraphBuilder $builder,
        private CitationGraphRepositoryPort $repository,
    ) {
    }

    public function handle(BuildCitationGraph $command): CitationGraph
    {
        $graph = match ($command->type) {
            CitationGraphType::CITATION => $this->builder->buildDirectCitationGraph(
                $command->projectId,
                $command->works,
                $command->referencesByWorkId,
            ),
            CitationGraphType::CO_CITATION => $this->builder->buildCoCitationGraph(
                $command->projectId,
                $command->works,
                $command->referencesByWorkId,
                $command->citingWorkIdsByCitedWorkId,
            ),
            CitationGraphType::BIBLIOGRAPHIC_COUPLING => $this->builder->buildBibliographicCouplingGraph(
                $command->projectId,
                $command->works,
                $command->referencesByWorkId,
            ),
        };

        if ($command->persist) {
            $this->repository->save($graph);
        }

        return $graph;
    }
}
