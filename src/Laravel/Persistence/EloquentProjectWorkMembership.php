<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class EloquentProjectWorkMembership implements ProjectCorpusWorksPort, ProjectWorkMembershipPort
{
    public function __construct(
        private readonly ?ProjectLockPort $locks = null,
        private readonly ?CorpusSnapshotRepositoryPort $snapshots = null,
    ) {}

    public function missingWorkIds(string $projectId, array $workIds): array
    {
        $workIds = $this->normalizeList($workIds);

        if ($workIds === []) {
            return [];
        }

        if ($this->locks?->isLocked($projectId)) {
            return $this->missingFromSnapshot($projectId, $workIds);
        }

        return $this->missingFromInferredMembership($projectId, $workIds);
    }

    public function workIds(string $projectId, array $queryIds = []): array
    {
        $queryIds = $this->normalizeList($queryIds);

        if ($this->locks?->isLocked($projectId)) {
            return $this->snapshotWorkIds($projectId, $queryIds);
        }

        return $this->inferredWorkIds($projectId, $queryIds);
    }

    /**
     * @param  list<string>  $workIds
     * @return list<string>
     */
    private function missingFromInferredMembership(string $projectId, array $workIds): array
    {
        return $this->missingFromBaseQuery(
            $workIds,
            fn (): Builder => $this->projectWorkBaseQuery($projectId),
            'query_works.work_id',
        );
    }

    /**
     * @param  list<string>  $workIds
     * @return list<string>
     */
    private function missingFromSnapshot(string $projectId, array $workIds): array
    {
        $snapshot = $this->snapshots?->latestForProject($projectId);

        if ($snapshot === null) {
            return $workIds;
        }

        return $this->missingFromBaseQuery(
            $workIds,
            fn (): Builder => $this->snapshotWorkBaseQuery($snapshot->id),
            'corpus_snapshot_works.work_id',
        );
    }

    /**
     * @param  list<string>  $workIds
     * @param  callable(): Builder  $baseQuery
     * @return list<string>
     */
    private function missingFromBaseQuery(array $workIds, callable $baseQuery, string $workColumn): array
    {
        $internalIds = [];
        $externalIds = [];

        foreach ($workIds as $workId) {
            $parsed = $this->parseWorkId($workId);

            if ($parsed?->namespace === WorkIdNamespace::INTERNAL) {
                $internalIds[$workId] = $parsed->value;

                continue;
            }

            if ($parsed instanceof WorkId) {
                $externalIds[$workId] = $parsed;

                continue;
            }

            $internalIds[$workId] = strtolower($workId);
        }

        $found = [];

        if ($internalIds !== []) {
            $rows = $baseQuery()
                ->whereIn($workColumn, array_values($internalIds))
                ->pluck($workColumn)
                ->map(static fn (mixed $value): string => strtolower((string) $value))
                ->all();
            $rowSet = array_fill_keys($rows, true);

            foreach ($internalIds as $original => $internalId) {
                if (isset($rowSet[strtolower($internalId)])) {
                    $found[$original] = true;
                }
            }
        }

        if ($externalIds !== []) {
            $rows = $baseQuery()
                ->join('work_external_ids', 'work_external_ids.work_id', '=', $workColumn)
                ->where(function ($query) use ($externalIds): void {
                    foreach ($externalIds as $workId) {
                        $query->orWhere(function ($nested) use ($workId): void {
                            $nested
                                ->where('work_external_ids.namespace', $workId->namespace->value)
                                ->where('work_external_ids.value', $workId->value);
                        });
                    }
                })
                ->get(['work_external_ids.namespace', 'work_external_ids.value']);

            foreach ($rows as $row) {
                $found[$row->namespace.':'.$row->value] = true;
            }

            foreach ($externalIds as $original => $workId) {
                if (isset($found[$workId->toString()])) {
                    $found[$original] = true;
                }
            }
        }

        return array_values(array_filter(
            $workIds,
            static fn (string $workId): bool => ! isset($found[$workId]),
        ));
    }

    /**
     * @param  list<string>  $queryIds
     * @return list<string>
     */
    private function inferredWorkIds(string $projectId, array $queryIds): array
    {
        $query = $this->projectWorkBaseQuery($projectId)
            ->select('query_works.work_id')
            ->orderBy('query_works.seen_at')
            ->orderBy('query_works.rank');

        if ($queryIds !== []) {
            $query->whereIn('search_queries.id', $queryIds);
        }

        return $query
            ->pluck('query_works.work_id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $queryIds
     * @return list<string>
     */
    private function snapshotWorkIds(string $projectId, array $queryIds): array
    {
        $snapshot = $this->snapshots?->latestForProject($projectId);

        if ($snapshot === null) {
            return [];
        }

        $query = $this->snapshotWorkBaseQuery($snapshot->id)
            ->select('corpus_snapshot_works.work_id', 'corpus_snapshot_works.search_query_ids')
            ->orderBy('corpus_snapshot_works.included_at')
            ->orderBy('corpus_snapshot_works.created_at');

        if ($queryIds === []) {
            return $query
                ->pluck('corpus_snapshot_works.work_id')
                ->map(static fn (mixed $value): string => (string) $value)
                ->unique()
                ->values()
                ->all();
        }

        $wanted = array_fill_keys($queryIds, true);
        $workIds = [];

        foreach ($query->get() as $row) {
            $sourceQueryIds = json_decode((string) $row->search_query_ids, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($sourceQueryIds)) {
                continue;
            }

            foreach ($sourceQueryIds as $sourceQueryId) {
                if (isset($wanted[(string) $sourceQueryId])) {
                    $workIds[(string) $row->work_id] = (string) $row->work_id;

                    break;
                }
            }
        }

        return array_values($workIds);
    }

    private function projectWorkBaseQuery(string $projectId): Builder
    {
        return DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $projectId);
    }

    private function snapshotWorkBaseQuery(string $snapshotId): Builder
    {
        return DB::table('corpus_snapshot_works')
            ->where('corpus_snapshot_works.snapshot_id', $snapshotId);
    }

    private function parseWorkId(string $workId): ?WorkId
    {
        try {
            return str_contains($workId, ':') ? WorkId::fromString($workId) : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normalizeList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => trim($value),
            $values,
        ), static fn (string $value): bool => $value !== '')));
    }
}
