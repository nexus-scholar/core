<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Port;

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;

interface SearchExecutorPort
{
    public function handle(SearchAcrossProviders $command): AggregatedResult;
}
