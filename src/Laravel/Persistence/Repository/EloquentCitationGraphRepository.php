<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\CitationGraphModel;
use Nexus\Laravel\Model\CitationEdgeModel;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphId;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class EloquentCitationGraphRepository implements CitationGraphRepositoryPort
{
    public function __construct(
        private readonly WorkRepositoryPort $workRepository
    ) {
    }

    public function save(CitationGraph $graph): void
    {
        DB::transaction(function () use ($graph): void {
            $graphRow = CitationGraphModel::updateOrCreate(
                ['id' => $graph->id->toString()],
                [
                    'type' => $graph->type->value,
                    // 'project_id' => ... // TODO: how to get project_id from graph?
                ]
            );

            CitationEdgeModel::where('graph_id', $graphRow->id)->delete();

            foreach ($graph->allEdges() as $edge) {
                CitationEdgeModel::create([
                    'id'       => (string) Str::uuid(),
                    'graph_id' => $graphRow->id,
                    'citing_work_id' => $edge->citing->toString(),
                    'cited_work_id'  => $edge->cited->toString(),
                    'weight'   => $edge->weight,
                ]);
            }
        });
    }

    public function findById(CitationGraphId $id): ?CitationGraph
    {
        $row = CitationGraphModel::with('edges')->find($id->toString());
        if (!$row) {
            return null;
        }

        return $this->toDomain($row);
    }

    /** @return CitationGraph[] */
    public function findByProjectId(string $projectId): array
    {
        return CitationGraphModel::where('project_id', $projectId)
            ->get()
            ->map(fn ($row) => $this->toDomain($row))
            ->all();
    }

    public function delete(CitationGraphId $id): void
    {
        CitationGraphModel::destroy($id->toString());
    }

    private function toDomain(CitationGraphModel $row): CitationGraph
    {
        $graph = CitationGraph::withId(
            new CitationGraphId($row->id),
            CitationGraphType::from($row->type)
        );

        // This is tricky because CitationGraph holds ScholarlyWork nodes.
        // We might need to fetch them if we want a fully hydrated graph.
        // For now, let's at least add the nodes we know about from edges.
        
        foreach ($row->edges as $edgeRow) {
            $citingId = WorkId::fromString($edgeRow->citing_work_id);
            $citedId  = WorkId::fromString($edgeRow->cited_work_id);
            
            // If the work is in our DB, we can load it.
            $citingWork = $this->workRepository->findById($citingId);
            if ($citingWork) {
                $graph->addWork($citingWork);
            }
            
            $graph->recordCitation($citingId, $citedId);
        }

        return $graph;
    }
}
