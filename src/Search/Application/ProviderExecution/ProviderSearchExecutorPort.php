<?php

declare(strict_types=1);

namespace Nexus\Search\Application\ProviderExecution;

use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\SearchQuery;

interface ProviderSearchExecutorPort
{
    /**
     * Provider execution details, including future concurrent fan-out, belong
     * behind this boundary. Callers receive only normalized works and stats.
     *
     * @param  AcademicProviderPort[]  $providers
     */
    public function execute(SearchQuery $query, array $providers): ProviderSearchExecutionResult;
}
