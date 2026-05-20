<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Infrastructure\Graph;

use Mbsoft\Graph\Algorithms\Centrality\DegreeCentrality;
use Mbsoft\Graph\Algorithms\Centrality\PageRank;
use Mbsoft\Graph\Algorithms\Components\Connected;
use Mbsoft\Graph\Algorithms\Components\StronglyConnected;
use Mbsoft\Graph\Algorithms\Decomposition\KCore;
use Mbsoft\Graph\Algorithms\Pathfinding\Dijkstra;
use Mbsoft\Graph\Domain\Graph;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationPath;
use Nexus\CitationNetwork\Domain\NetworkMetrics;
use Nexus\CitationNetwork\Domain\Port\GraphAlgorithmPort;
use Nexus\Shared\ValueObject\WorkId;

final class MbsoftNetworkMetricsCalculator implements GraphAlgorithmPort
{
    private readonly MbsoftCitationGraphMapper $mapper;

    public function __construct(?MbsoftCitationGraphMapper $mapper = null)
    {
        $this->mapper = $mapper ?? new MbsoftCitationGraphMapper();
    }

    public function compute(CitationGraph $graph): NetworkMetrics
    {
        $mbsoftGraph = $this->mapper->toGraph($graph);

        return new NetworkMetrics(
            pageRank: $this->normalizeFloatMap((new PageRank())->compute($mbsoftGraph)),
            inDegree: $this->normalizeFloatMap(
                (new DegreeCentrality(DegreeCentrality::IN_DEGREE))->compute($mbsoftGraph)
            ),
            outDegree: $this->normalizeFloatMap(
                (new DegreeCentrality(DegreeCentrality::OUT_DEGREE))->compute($mbsoftGraph)
            ),
            totalDegree: $this->normalizeFloatMap(
                (new DegreeCentrality(DegreeCentrality::TOTAL_DEGREE))->compute($mbsoftGraph)
            ),
            coreNumbers: $this->normalizeIntegerMap((new KCore())->compute($mbsoftGraph)),
            weakComponents: (new Connected())->findComponents($mbsoftGraph),
            stronglyConnectedComponents: $mbsoftGraph->isDirected()
                ? (new StronglyConnected())->findComponents($mbsoftGraph)
                : [],
            nodeCount: count($mbsoftGraph->nodes()),
            edgeCount: count($mbsoftGraph->edges()),
            density: $this->density($mbsoftGraph),
        );
    }

    public function shortestPath(CitationGraph $graph, WorkId $source, WorkId $target): ?CitationPath
    {
        $result = (new Dijkstra())->find(
            $this->mapper->toGraph($graph),
            $source->toString(),
            $target->toString(),
        );

        if ($result === null) {
            return null;
        }

        return new CitationPath($result->nodes, $result->cost);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, float>
     */
    private function normalizeFloatMap(array $values): array
    {
        $normalized = [];

        foreach ($values as $id => $value) {
            $normalized[(string) $id] = (float) $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, int>
     */
    private function normalizeIntegerMap(array $values): array
    {
        $normalized = [];

        foreach ($values as $id => $value) {
            $normalized[(string) $id] = (int) $value;
        }

        return $normalized;
    }

    private function density(Graph $graph): float
    {
        $nodeCount = count($graph->nodes());

        if ($nodeCount <= 1) {
            return 0.0;
        }

        $edgeCount = count($graph->edges());
        $possibleEdges = $nodeCount * ($nodeCount - 1);

        if (! $graph->isDirected()) {
            $possibleEdges /= 2;
        }

        return $edgeCount / $possibleEdges;
    }
}
