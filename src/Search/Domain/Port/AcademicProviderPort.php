<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

interface AcademicProviderPort
{
    /**
     * Provider machine name. Must be stable, lowercase, snake_case.
     * Examples: 'openalex', 'semantic_scholar', 'arxiv', 'crossref',
     *           'pubmed', 'ieee', 'doaj'
     */
    public function alias(): string;

    /**
     * Search for works matching the query.
     *
     * MUST call $this->rateLimiter->waitForToken() before each HTTP request.
     * MUST respect $query->maxResults and $query->offset.
     * MUST NOT store $rawData on returned works unless $query->includeRawData.
     * MUST return an empty array (not throw) when provider returns 0 results.
     *
     * @return ScholarlyWork[]
     * @throws \Nexus\Search\Domain\Exception\ProviderUnavailable on HTTP 5xx or connection failure after retries
     */
    public function search(SearchQuery $query): array;

    /**
     * Fetch a single work by known external identifier.
     * Returns null if the provider cannot find or does not support this ID.
     *
     * @throws \Nexus\Search\Domain\Exception\ProviderUnavailable on network failure
     */
    public function fetchById(WorkId $id): ?ScholarlyWork;

    /**
     * Whether this provider can resolve a given namespace.
     * Used to skip unnecessary fetchById calls.
     */
    public function supports(WorkIdNamespace $ns): bool;
}
