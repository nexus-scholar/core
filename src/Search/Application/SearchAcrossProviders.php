<?php

declare(strict_types=1);

namespace Nexus\Search\Application;

use Nexus\Search\Domain\SearchQuery;

final class SearchAcrossProviders
{
    public function __construct(
        public readonly SearchQuery $query,
        public readonly array       $providerAliases = [],
        // empty array = use all registered providers
        public readonly int         $timeoutMs = 60000,
    ) {}
}
