<?php

declare(strict_types=1);

namespace Nexus\Search\Application\UseCase;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;

/**
 * Command to search for scholarly works across all configured providers.
 */
final class SearchAcrossProviders
{
    public readonly SearchQuery $query;

    public function __construct(
        string $query,
        int    $maxResults = 50,
        ?int   $yearFrom = null,
        ?int   $yearTo = null,
    ) {
        $yearRange = ($yearFrom !== null || $yearTo !== null)
            ? new YearRange($yearFrom, $yearTo)
            : null;

        $this->query = new SearchQuery(
            term:       new SearchTerm($query),
            maxResults: $maxResults,
            yearRange:  $yearRange
        );
    }
}
