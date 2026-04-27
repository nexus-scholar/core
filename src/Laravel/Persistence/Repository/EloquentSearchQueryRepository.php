<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Illuminate\Support\Str;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\SearchQueryProviderModel;
use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Domain\ProviderProgress;
use Nexus\Shared\ValueObject\LanguageCode;
use Nexus\Search\Domain\Port\SearchQueryRepositoryPort;

final class EloquentSearchQueryRepository implements SearchQueryRepositoryPort
{
    public function save(SearchQuery $query): void
    {
        $data = [
            'project_id'  => $query->projectId,
            'query_text'  => $query->term->value,
            'from_year'   => $query->yearRange?->from,
            'to_year'     => $query->yearRange?->to,
            'language'    => $query->language?->value,
            'max_results' => $query->maxResults,
            'offset'      => $query->offset,
            'include_raw_data' => $query->includeRawData,
            'cache_key'   => $query->cacheKey(),
        ];

        SearchQueryModel::updateOrCreate(
            ['id' => $query->id],
            $data
        );
    }

    public function recordProviderProgress(
        string           $searchQueryId,
        string           $providerAlias,
        ProviderProgress $progress
    ): void {
        SearchQueryProviderModel::updateOrCreate(
            [
                'search_query_id' => $searchQueryId,
                'provider_alias'  => $providerAlias,
            ],
            array_merge(
                ['id' => (string) Str::uuid()],
                $progress->toArray()
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
        // workId passed here might be the toString() with prefix, but query_works FK 
        // references scholarly_works.id which is the bare value.
        $bareWorkId = str_contains($workId, ':') ? explode(':', $workId, 2)[1] : $workId;

        QueryWorkModel::firstOrCreate(
            [
                'search_query_id' => $searchQueryId,
                'work_id'         => $bareWorkId,
                'provider_alias'  => $providerAlias,
            ],
            [
                'id'               => (string) Str::uuid(),
                'provider_work_id' => $providerWorkId,
                'rank'             => $rank,
            ]
        );
    }

    public function findById(string $id): ?SearchQuery
    {
        $row = SearchQueryModel::find($id);
        if (!$row) {
            return null;
        }

        return new SearchQuery(
            term: new SearchTerm($row->query_text),
            projectId: $row->project_id,
            yearRange: ($row->from_year || $row->to_year) 
                ? new YearRange($row->from_year, $row->to_year) 
                : null,
            language: $row->language ? new LanguageCode($row->language) : null,
            maxResults: $row->max_results,
            offset: $row->offset ?? 0,
            includeRawData: (bool) ($row->include_raw_data ?? false),
            id: $row->id
        );
    }

    public function findByProject(string $projectId): array
    {
        return SearchQueryModel::where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => $this->findById($row->id))
            ->all();
    }
}
