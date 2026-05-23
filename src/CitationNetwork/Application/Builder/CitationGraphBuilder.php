<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\Builder;

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;

final class CitationGraphBuilder
{
    /**
     * @param  list<ScholarlyWork>  $works
     * @param  array<string, list<WorkId|string>>  $referencesByCitingWorkId
     */
    public function buildDirectCitationGraph(
        string $projectId,
        array $works,
        array $referencesByCitingWorkId,
    ): CitationGraph {
        $graph = $this->newGraph(CitationGraphType::CITATION, $projectId, $works);
        $workIndex = $this->indexWorksByAllIds($works);

        foreach ($works as $work) {
            $citingId = $work->primaryId();

            if ($citingId === null) {
                continue;
            }

            foreach ($this->referencesFor($work, $referencesByCitingWorkId) as $rawReference) {
                $referenceId = $this->normalizeId($rawReference);

                if ($referenceId === null) {
                    continue;
                }

                $citedWork = $workIndex[$referenceId->toString()] ?? null;
                $citedId = $citedWork?->primaryId();

                if ($citedId === null) {
                    continue;
                }

                $graph->recordCitation($citingId, $citedId);
            }
        }

        return $graph;
    }

    /**
     * @param  list<ScholarlyWork>  $works
     * @param  array<string, list<WorkId|string>>  $referencesByCitingWorkId
     * @param  array<string, list<WorkId|string>>  $citingWorkIdsByCitedWorkId
     */
    public function buildCoCitationGraph(
        string $projectId,
        array $works,
        array $referencesByCitingWorkId = [],
        array $citingWorkIdsByCitedWorkId = [],
    ): CitationGraph {
        $graph = $this->newGraph(CitationGraphType::CO_CITATION, $projectId, $works);
        $workIndex = $this->indexWorksByAllIds($works);
        $citedIdsByCitingId = $this->knownCitedIdsByCitingId(
            $referencesByCitingWorkId,
            $citingWorkIdsByCitedWorkId,
            $workIndex,
        );

        foreach ($this->pairCountsFromGroupedIds($citedIdsByCitingId) as $left => $neighbors) {
            foreach ($neighbors as $right => $weight) {
                $graph->recordCitation(WorkId::fromString($left), WorkId::fromString($right), (float) $weight);
            }
        }

        return $graph;
    }

    /**
     * @param  list<ScholarlyWork>  $works
     * @param  array<string, list<WorkId|string>>  $referencesByWorkId
     */
    public function buildBibliographicCouplingGraph(
        string $projectId,
        array $works,
        array $referencesByWorkId,
    ): CitationGraph {
        $graph = $this->newGraph(CitationGraphType::BIBLIOGRAPHIC_COUPLING, $projectId, $works);
        $referenceToWorks = [];

        foreach ($works as $work) {
            $workId = $work->primaryId();

            if ($workId === null) {
                continue;
            }

            foreach ($this->referencesFor($work, $referencesByWorkId) as $rawReference) {
                $referenceId = $this->normalizeId($rawReference);

                if ($referenceId === null) {
                    continue;
                }

                $referenceToWorks[$referenceId->toString()][$workId->toString()] = true;
            }
        }

        foreach ($this->pairCountsFromGroupedIds($referenceToWorks) as $left => $neighbors) {
            foreach ($neighbors as $right => $weight) {
                $graph->recordCitation(WorkId::fromString($left), WorkId::fromString($right), (float) $weight);
            }
        }

        return $graph;
    }

    /**
     * @param  list<ScholarlyWork>  $works
     */
    private function newGraph(CitationGraphType $type, string $projectId, array $works): CitationGraph
    {
        $graph = CitationGraph::create($type, $projectId);

        foreach ($works as $work) {
            $graph->addWork($work);
        }

        return $graph;
    }

    /**
     * @param  list<ScholarlyWork>  $works
     * @return array<string, ScholarlyWork>
     */
    private function indexWorksByAllIds(array $works): array
    {
        $index = [];

        foreach ($works as $work) {
            foreach ($work->ids()->all() as $id) {
                $index[$id->toString()] = $work;
            }
        }

        return $index;
    }

    /**
     * @param  array<string, list<WorkId|string>>  $referencesByWorkId
     * @return list<WorkId|string>
     */
    private function referencesFor(ScholarlyWork $work, array $referencesByWorkId): array
    {
        foreach ($work->ids()->all() as $id) {
            if (isset($referencesByWorkId[$id->toString()])) {
                return array_values($referencesByWorkId[$id->toString()]);
            }
        }

        return [];
    }

    /**
     * @param  array<string, list<WorkId|string>>  $referencesByCitingWorkId
     * @param  array<string, list<WorkId|string>>  $citingWorkIdsByCitedWorkId
     * @param  array<string, ScholarlyWork>  $workIndex
     * @return array<string, array<string, true>>
     */
    private function knownCitedIdsByCitingId(
        array $referencesByCitingWorkId,
        array $citingWorkIdsByCitedWorkId,
        array $workIndex,
    ): array {
        $citedIdsByCitingId = [];

        foreach ($referencesByCitingWorkId as $citingId => $references) {
            foreach ($references as $rawReference) {
                $referenceId = $this->normalizeId($rawReference);
                $citedWork = $referenceId === null ? null : ($workIndex[$referenceId->toString()] ?? null);
                $citedId = $citedWork?->primaryId();

                if ($citedId !== null) {
                    $citedIdsByCitingId[$citingId][$citedId->toString()] = true;
                }
            }
        }

        foreach ($citingWorkIdsByCitedWorkId as $citedIdString => $citingIds) {
            $citedId = $this->normalizeId($citedIdString);
            $citedWork = $citedId === null ? null : ($workIndex[$citedId->toString()] ?? null);
            $knownCitedId = $citedWork?->primaryId();

            if ($knownCitedId === null) {
                continue;
            }

            foreach ($citingIds as $rawCitingId) {
                $citingId = $this->normalizeId($rawCitingId);

                if ($citingId !== null) {
                    $citedIdsByCitingId[$citingId->toString()][$knownCitedId->toString()] = true;
                }
            }
        }

        return $citedIdsByCitingId;
    }

    /**
     * @param  array<string, array<string, true>>  $groups
     * @return array<string, array<string, int>>
     */
    private function pairCountsFromGroupedIds(array $groups): array
    {
        $counts = [];

        foreach ($groups as $ids) {
            $uniqueIds = array_keys($ids);
            sort($uniqueIds);
            $total = count($uniqueIds);

            for ($i = 0; $i < $total; $i++) {
                for ($j = $i + 1; $j < $total; $j++) {
                    $counts[$uniqueIds[$i]][$uniqueIds[$j]] = ($counts[$uniqueIds[$i]][$uniqueIds[$j]] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    private function normalizeId(WorkId|string $id): ?WorkId
    {
        if ($id instanceof WorkId) {
            return $id;
        }

        try {
            return WorkId::fromString($id);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
