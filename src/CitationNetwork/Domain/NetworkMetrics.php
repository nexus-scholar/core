<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain;

use Nexus\Shared\ValueObject\WorkId;

final readonly class NetworkMetrics
{
    /**
     * @param array<string, float> $pageRank
     * @param array<string, float> $inDegree
     * @param array<string, float> $outDegree
     * @param array<string, float> $totalDegree
     * @param array<string, int> $coreNumbers
     * @param list<list<string>> $weakComponents
     * @param list<list<string>> $stronglyConnectedComponents
     */
    public function __construct(
        public array $pageRank = [],
        public array $inDegree = [],
        public array $outDegree = [],
        public array $totalDegree = [],
        public array $coreNumbers = [],
        public array $weakComponents = [],
        public array $stronglyConnectedComponents = [],
        public int $nodeCount = 0,
        public int $edgeCount = 0,
        public float $density = 0.0,
    ) {
    }

    public function pageRankOf(WorkId $id): float
    {
        return $this->pageRank[$id->toString()] ?? 0.0;
    }

    public function inDegreeOf(WorkId $id): float
    {
        return $this->inDegree[$id->toString()] ?? 0.0;
    }

    public function outDegreeOf(WorkId $id): float
    {
        return $this->outDegree[$id->toString()] ?? 0.0;
    }

    public function totalDegreeOf(WorkId $id): float
    {
        return $this->totalDegree[$id->toString()] ?? 0.0;
    }

    public function coreNumberOf(WorkId $id): ?int
    {
        return $this->coreNumbers[$id->toString()] ?? null;
    }

    /**
     * @return array{
     *     page_rank: array<string, float>,
     *     in_degree: array<string, float>,
     *     out_degree: array<string, float>,
     *     total_degree: array<string, float>,
     *     core_numbers: array<string, int>,
     *     weak_components: list<list<string>>,
     *     strongly_connected_components: list<list<string>>,
     *     node_count: int,
     *     edge_count: int,
     *     density: float
     * }
     */
    public function toArray(): array
    {
        return [
            'page_rank' => $this->pageRank,
            'in_degree' => $this->inDegree,
            'out_degree' => $this->outDegree,
            'total_degree' => $this->totalDegree,
            'core_numbers' => $this->coreNumbers,
            'weak_components' => $this->weakComponents,
            'strongly_connected_components' => $this->stronglyConnectedComponents,
            'node_count' => $this->nodeCount,
            'edge_count' => $this->edgeCount,
            'density' => $this->density,
        ];
    }
}
