<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Aggregator;

use Nexus\Search\Domain\SearchQuery;

interface SearchAggregatorPort
{
    public function aggregate(SearchQuery $query): AggregatedResult;
}
