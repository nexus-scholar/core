<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Infrastructure\Graph;

use Mbsoft\Graph\Domain\Graph;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;

final class MbsoftCitationGraphMapper
{
    public function toGraph(CitationGraph $citationGraph): Graph
    {
        $graph = new Graph(
            directed: $citationGraph->type === CitationGraphType::CITATION
        );

        foreach ($citationGraph->allWorks() as $work) {
            $workId = $work->primaryId();

            if ($workId === null) {
                continue;
            }

            $graph->addNode($workId->toString(), $this->workAttributes($work, $workId));
        }

        foreach ($citationGraph->allEdges() as $edge) {
            $this->ensureNode($graph, $edge->citing);
            $this->ensureNode($graph, $edge->cited);

            $graph->addEdge($edge->citing->toString(), $edge->cited->toString(), [
                'weight' => $edge->weight,
                'type' => $citationGraph->type->value,
            ]);
        }

        return $graph;
    }

    /**
     * @return array<string, mixed>
     */
    private function workAttributes(ScholarlyWork $work, WorkId $workId): array
    {
        return [
            'id' => $workId->toString(),
            'namespace' => $workId->namespace->value,
            'value' => $workId->value,
            'title' => $work->title(),
            'year' => $work->year(),
            'source_provider' => $work->sourceProvider(),
            'cited_by_count' => $work->citedByCount(),
            'external' => false,
        ];
    }

    private function ensureNode(Graph $graph, WorkId $workId): void
    {
        if ($graph->hasNode($workId->toString())) {
            return;
        }

        $graph->addNode($workId->toString(), [
            'id' => $workId->toString(),
            'namespace' => $workId->namespace->value,
            'value' => $workId->value,
            'external' => true,
        ]);
    }
}
