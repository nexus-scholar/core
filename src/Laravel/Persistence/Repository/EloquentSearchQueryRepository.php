<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Illuminate\Support\Str;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\SearchQueryProviderModel;
use Nexus\Laravel\Model\QueryWorkModel;

final class EloquentSearchQueryRepository
{
    public function save(array $queryData): void
    {
        SearchQueryModel::updateOrCreate(
            ['id' => $queryData['id']],
            $queryData
        );
    }

    public function recordProviderProgress(
        string $searchQueryId,
        string $providerAlias,
        array $progressData
    ): void {
        SearchQueryProviderModel::updateOrCreate(
            [
                'search_query_id' => $searchQueryId,
                'provider_alias'  => $providerAlias,
            ],
            array_merge(
                ['id' => (string) Str::uuid()],
                $progressData
            )
        );
    }

    public function linkWorkToQuery(
        string $searchQueryId,
        string $workId,
        string $providerAlias,
        string $providerWorkId,
        int $rank
    ): void {
        QueryWorkModel::firstOrCreate(
            [
                'search_query_id' => $searchQueryId,
                'work_id'         => $workId,
                'provider_alias'  => $providerAlias,
            ],
            [
                'id'               => (string) Str::uuid(),
                'provider_work_id' => $providerWorkId,
                'rank'             => $rank,
            ]
        );
    }

    public function findById(string $id): ?SearchQueryModel
    {
        return SearchQueryModel::with(['providerProgress', 'queryWorks'])->find($id);
    }

    public function findByProject(string $projectId): array
    {
        return SearchQueryModel::where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }
}

