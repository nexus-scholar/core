<?php

declare(strict_types=1);

namespace Nexus\Search\Application;

use Nexus\Search\Domain\CorpusSlice;

final class SearchAcrossProvidersResult
{
    public function __construct(
        public readonly CorpusSlice $corpus,
        /** @var ProviderSearchResult[] */
        public readonly array       $providerResults,
        public readonly bool        $fromCache,
        public readonly int         $durationMs,
    ) {}
}
