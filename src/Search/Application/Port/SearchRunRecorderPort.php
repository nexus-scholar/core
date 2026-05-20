<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Port;

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\ProviderStat;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Throwable;

interface SearchRunRecorderPort
{
    public function recordStarted(SearchQuery $query): void;

    public function recordProviderStat(SearchQuery $query, ProviderStat $stat): void;

    public function recordWork(
        SearchQuery $query,
        ScholarlyWork $work,
        string $providerAlias,
        string $providerWorkId,
        int $rank,
    ): void;

    public function recordCompleted(SearchQuery $query, AggregatedResult $result): void;

    public function recordFailed(SearchQuery $query, Throwable $error): void;
}
