<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain;

use Nexus\CitationNetwork\Domain\Exception\WorkNotInGraph;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;

final class CitationGraph
{
    /** @var array<string, ScholarlyWork> key = WorkId::toString() */
    private array $nodes = [];

    /** @var CitationLink[] */
    private array $edges = [];

    private function __construct(
        public readonly CitationGraphId $id,
        public readonly CitationGraphType $type,
        public readonly string $projectId,
    ) {}

    public static function create(CitationGraphType $type, string $projectId): self
    {
        return new self(CitationGraphId::generate(), $type, $projectId);
    }

    public static function withId(CitationGraphId $id, CitationGraphType $type, string $projectId): self
    {
        return new self($id, $type, $projectId);
    }

    public function addWork(ScholarlyWork $work): void
    {
        $idStr = $work->primaryId()?->toString();
        if ($idStr === null) {
            return;
        }
        $this->nodes[$idStr] = $work;
    }

    /**
     * Record that $citing cites $cited.
     */
    public function recordCitation(WorkId $citing, WorkId $cited, float $weight = 1.0): void
    {
        if (! $this->hasWork($citing)) {
            throw new WorkNotInGraph($citing);
        }

        foreach ($this->edges as $edge) {
            if ($edge->citing->equals($citing) && $edge->cited->equals($cited)) {
                return;
            }
        }

        $this->edges[] = new CitationLink($citing, $cited, $weight);
    }

    public function hasWork(WorkId $id): bool
    {
        return isset($this->nodes[$id->toString()]);
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    public function edgeCount(): int
    {
        return count($this->edges);
    }

    /** @return ScholarlyWork[] */
    public function allWorks(): array
    {
        return array_values($this->nodes);
    }

    /** @return CitationLink[] */
    public function allEdges(): array
    {
        return $this->edges;
    }

    public function workByIdString(string $s): ?ScholarlyWork
    {
        if (isset($this->nodes[$s])) {
            return $this->nodes[$s];
        }

        foreach ($this->nodes as $idString => $work) {
            if (str_contains($idString, ':') && explode(':', $idString, 2)[1] === $s) {
                return $work;
            }
        }

        return null;
    }
}
