<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

use Nexus\Search\Domain\SearchQuery;

interface SearchQueryRepositoryPort
{
    public function findById(string $id): ?SearchQuery;
    public function save(SearchQuery $query): void;

    public function recordProviderProgress(
        string $searchQueryId,
        string $providerAlias,
        array $progressData
    ): void;

    public function linkWorkToQuery(
        string $searchQueryId,
        string $workId,
        string $providerAlias,
        string $providerWorkId,
        int $rank
    ): void;
}
