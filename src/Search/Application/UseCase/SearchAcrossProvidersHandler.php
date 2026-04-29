<?php

declare(strict_types=1);

namespace Nexus\Search\Application\UseCase;

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Illuminate\Support\Facades\DB;
use Nexus\Shared\Exception\ProjectLockedException;

/**
 * Orchestrates a concurrent search across all active academic providers.
 * Delegates to the SearchAggregator for parallel execution and deduplication.
 */
final class SearchAcrossProvidersHandler
{
    public function __construct(
        private readonly SearchAggregatorPort $aggregator,
    ) {}

    public function handle(SearchAcrossProviders $command): AggregatedResult
    {
        $isLocked = DB::table('projects')
            ->where('id', $command->query->projectId)
            ->whereNotNull('locked_at')
            ->exists();

        if ($isLocked) {
            throw new ProjectLockedException("Cannot perform search on locked project {$command->query->projectId}");
        }

        return $this->aggregator->aggregate($command->query);
    }
}
