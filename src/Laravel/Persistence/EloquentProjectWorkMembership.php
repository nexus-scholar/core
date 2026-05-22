<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class EloquentProjectWorkMembership implements ProjectWorkMembershipPort
{
    public function missingWorkIds(string $projectId, array $workIds): array
    {
        $workIds = array_values(array_unique(array_filter(array_map(
            static fn (string $workId): string => trim($workId),
            $workIds,
        ), static fn (string $workId): bool => $workId !== '')));

        if ($workIds === []) {
            return [];
        }

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
            $rows = $this->projectWorkBaseQuery($projectId)
                ->whereIn('query_works.work_id', array_values($internalIds))
                ->pluck('query_works.work_id')
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
            $rows = $this->projectWorkBaseQuery($projectId)
                ->join('work_external_ids', 'work_external_ids.work_id', '=', 'query_works.work_id')
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

    private function projectWorkBaseQuery(string $projectId): Builder
    {
        return DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $projectId);
    }

    private function parseWorkId(string $workId): ?WorkId
    {
        try {
            return str_contains($workId, ':') ? WorkId::fromString($workId) : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
