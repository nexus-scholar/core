<?php

declare(strict_types=1);

namespace Nexus\Search\Application\UseCase;

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Port\ProjectLockPort;

/**
 * Orchestrates a concurrent search across all active academic providers.
 * Delegates to the SearchAggregator for parallel execution and deduplication.
 */
final class SearchAcrossProvidersHandler
{
    public function __construct(
        private readonly SearchAggregatorPort $aggregator,
        private readonly ProjectLockPort $projectLocks,
    ) {}

    public function handle(SearchAcrossProviders $command): AggregatedResult
    {
        if ($this->projectLocks->isLocked($command->query->projectId)) {
            throw new ProjectLockedException("Cannot perform search on locked project {$command->query->projectId}");
        }

        return $this->aggregator->aggregate($command->query);
    }
}
