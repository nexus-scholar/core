<?php

declare(strict_types=1);

namespace Nexus\Search\Application\UseCase;

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;

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
        return $this->aggregator->aggregate($command->query);
    }
}
