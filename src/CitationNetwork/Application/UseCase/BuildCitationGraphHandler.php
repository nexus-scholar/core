<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\CorpusOperation;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final readonly class BuildCitationGraphHandler
{
    public function __construct(
        private CitationGraphBuilder $builder,
        private CitationGraphRepositoryPort $repository,
        private ?CorpusLockPolicy $lockPolicy = null,
    ) {}

    public function handle(BuildCitationGraph $command): CitationGraph
    {
        if ($command->persist && $this->lockPolicy?->isLocked($command->projectId)) {
            $this->lockPolicy->assertWorksBelongToProject(
                $command->projectId,
                $this->workIdentifiers($command->works),
                CorpusOperation::BUILD_GRAPH,
            );
        }

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

    /**
     * @param  list<ScholarlyWork>  $works
     * @return list<string>
     */
    private function workIdentifiers(array $works): array
    {
        $identifiers = [];

        foreach ($works as $work) {
            $identifier = $work->ids()->findByNamespace(WorkIdNamespace::INTERNAL)
                ?? $work->primaryId();

            if ($identifier !== null) {
                $identifiers[] = $identifier->toString();
            }
        }

        return $identifiers;
    }
}
