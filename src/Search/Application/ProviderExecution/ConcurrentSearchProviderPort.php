<?php

declare(strict_types=1);

namespace Nexus\Search\Application\ProviderExecution;

use Nexus\Search\Domain\SearchQuery;

interface ConcurrentSearchProviderPort
{
    /**
     * Start a provider search without blocking until the result is needed.
     *
     * Returning null tells the executor to fall back to the provider's
     * synchronous AcademicProviderPort::search() implementation.
     */
    public function beginSearch(SearchQuery $query): ?ProviderSearchTask;
}
