<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Screening\Application\Port\ScreeningWorkSourcePort;
use Nexus\Screening\Domain\ScreeningWork;

final class EloquentScreeningWorkSource implements ScreeningWorkSourcePort
{
    public function forProject(string $projectId, ?int $limit = null, array $workIds = [], array $queryIds = []): array
    {
        $query = ScholarlyWorkModel::query()
            ->with(['externalIds', 'providers'])
            ->whereExists(function ($subquery) use ($projectId, $queryIds): void {
                $subquery
                    ->selectRaw('1')
                    ->from('query_works')
                    ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
                    ->whereColumn('query_works.work_id', 'scholarly_works.id')
                    ->where('search_queries.project_id', $projectId);

                if ($queryIds !== []) {
                    $subquery->whereIn('search_queries.id', $queryIds);
                }
            })
            ->orderBy('title');

        if ($workIds !== []) {
            $query->whereIn('id', $workIds);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query
            ->get()
            ->map(fn (ScholarlyWorkModel $work): ScreeningWork => $this->toScreeningWork($work, $projectId, $queryIds))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $queryIds
     */
    private function toScreeningWork(ScholarlyWorkModel $work, string $projectId, array $queryIds): ScreeningWork
    {
        $providerAliases = $this->providerAliases($work->id, $projectId, $queryIds);

        return new ScreeningWork(
            id: (string) $work->id,
            title: (string) $work->title,
            abstract: $work->abstract === null ? null : (string) $work->abstract,
            year: $work->year === null ? null : (int) $work->year,
            venue: $work->venue_name === null ? null : (string) $work->venue_name,
            sourceProvider: $this->sourceProvider($work, $providerAliases),
            identifiers: $this->identifiers($work),
            metadata: [
                'provider_aliases' => $providerAliases,
                'is_retracted' => (bool) $work->is_retracted,
                'cited_by_count' => $work->cited_by_count === null ? null : (int) $work->cited_by_count,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function identifiers(ScholarlyWorkModel $work): array
    {
        $identifiers = [];

        foreach ($work->externalIds as $externalId) {
            $namespace = (string) $externalId->namespace;
            if (! isset($identifiers[$namespace])) {
                $identifiers[$namespace] = (string) $externalId->value;
            }
        }

        return $identifiers;
    }

    /**
     * @param  list<string>  $providerAliases
     */
    private function sourceProvider(ScholarlyWorkModel $work, array $providerAliases): ?string
    {
        $provider = $work->providers
            ->sortByDesc(fn ($provider) => $provider->last_seen_at?->getTimestamp() ?? 0)
            ->first();

        if ($provider !== null) {
            return (string) $provider->provider_alias;
        }

        return $providerAliases[0] ?? null;
    }

    /**
     * @param  list<string>  $queryIds
     * @return list<string>
     */
    private function providerAliases(string $workId, string $projectId, array $queryIds): array
    {
        $query = QueryWorkModel::query()
            ->select('query_works.provider_alias')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('query_works.work_id', $workId)
            ->where('search_queries.project_id', $projectId)
            ->whereNotNull('query_works.provider_alias')
            ->orderBy('query_works.rank');

        if ($queryIds !== []) {
            $query->whereIn('search_queries.id', $queryIds);
        }

        return $query
            ->pluck('query_works.provider_alias')
            ->filter()
            ->unique()
            ->values()
            ->map(static fn (mixed $alias): string => (string) $alias)
            ->all();
    }
}
