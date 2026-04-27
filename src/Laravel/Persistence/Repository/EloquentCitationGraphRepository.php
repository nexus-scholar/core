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
                    'graph_type' => $graph->type->value,
                    'project_id' => $graph->projectId,
                    'node_count' => $graph->nodeCount(),
                    'edge_count' => $graph->edgeCount(),
                ]
            );

            CitationEdgeModel::where('graph_id', $graphRow->id)->delete();

            foreach ($graph->allEdges() as $edge) {
                CitationEdgeModel::create([
                    'id'             => (string) Str::uuid(),
                    'graph_id'       => $graphRow->id,
                    'citing_work_id' => $edge->citing->value,
                    'cited_work_id'  => $edge->cited->value,
                    'weight'         => $edge->weight,
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
        return CitationGraphModel::with('edges')->where('project_id', $projectId)
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
            CitationGraphType::from($row->graph_type),
            $row->project_id
        );

        $works = [];
        // Collect all unique work IDs across both sides of every edge
        $workIdMap = [];
        foreach ($row->edges as $edge) {
            $workIdMap[$edge->citing_work_id] = true;
            $workIdMap[$edge->cited_work_id]  = true;
        }

        if (!empty($workIdMap)) {
            $workIds = array_map(fn ($idStr) => new WorkId(WorkIdNamespace::INTERNAL, $idStr), array_keys($workIdMap));
            $works = $this->workRepository->findManyByIds($workIds);

            foreach ($works as $work) {
                $graph->addWork($work);
            }
        }

        // Create a map from internal ID to work to resolve edges to primary IDs
        $internalToWork = [];
        foreach ($works as $work) {
            $internalId = $work->ids()->findByNamespace(WorkIdNamespace::INTERNAL);
            if ($internalId) {
                $internalToWork[$internalId->value] = $work;
            }
        }

        // Now record edges — nodes must exist for recordCitation to accept them
        foreach ($row->edges as $edgeRow) {
            $citingWork = $internalToWork[$edgeRow->citing_work_id] ?? null;
            $citedWork  = $internalToWork[$edgeRow->cited_work_id] ?? null;

            if ($citingWork && $citedWork) {
                $graph->recordCitation($citingWork->primaryId(), $citedWork->primaryId());
            }
        }

        return $graph;
    }
}
