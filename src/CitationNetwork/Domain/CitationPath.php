<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain;

final readonly class CitationPath
{
    /**
     * @param list<string> $nodes Work ID strings in traversal order.
     */
    public function __construct(
        public array $nodes,
        public float $cost,
    ) {
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    public function edgeCount(): int
    {
        return max(0, $this->nodeCount() - 1);
    }

    /**
     * @return array{nodes: list<string>, cost: float, node_count: int, edge_count: int}
     */
    public function toArray(): array
    {
        return [
            'nodes' => $this->nodes,
            'cost' => $this->cost,
            'node_count' => $this->nodeCount(),
            'edge_count' => $this->edgeCount(),
        ];
    }
}
