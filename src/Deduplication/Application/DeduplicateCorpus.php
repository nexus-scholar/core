<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

use Nexus\Search\Domain\CorpusSlice;

final class DeduplicateCorpus
{
    public function __construct(
        public readonly CorpusSlice $corpus,
        public readonly array       $policyAliases = [],
        // empty = use all registered policies in default order
    ) {}
}
