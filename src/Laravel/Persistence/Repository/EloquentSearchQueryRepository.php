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
        SearchQueryModel::updateOrCreate(
            ['id' => $query->id],
            [
                'term'        => $query->term->value,
                'year_from'   => $query->yearRange?->from,
                'year_to'     => $query->yearRange?->to,
                'language'    => $query->language?->value,
                'max_results' => $query->maxResults,
            ]
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

    public function findById(string $id): ?SearchQuery
    {
        $row = SearchQueryModel::find($id);
        if (!$row) {
            return null;
        }

        return new SearchQuery(
            term: new SearchTerm($row->term),
            yearRange: ($row->year_from || $row->year_to) 
                ? new YearRange($row->year_from, $row->year_to) 
                : null,
            language: $row->language ? LanguageCode::tryFrom($row->language) : null,
            maxResults: $row->max_results,
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
