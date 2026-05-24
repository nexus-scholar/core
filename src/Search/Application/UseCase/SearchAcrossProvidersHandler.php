<?php

declare(strict_types=1);

namespace Nexus\Search\Application\UseCase;

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Port\ProjectLockPort;

/**
 * Orchestrates search across active academic providers.
 * Delegates provider execution and deduplication to the SearchAggregator.
 */
final class SearchAcrossProvidersHandler implements SearchExecutorPort
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
