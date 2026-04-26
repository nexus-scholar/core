<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

use Nexus\Deduplication\Domain\DedupClusterCollection;

final class DeduplicateCorpusResult
{
    public function __construct(
        public readonly DedupClusterCollection $clusters,
        public readonly int                    $inputCount,
        public readonly int                    $uniqueCount,
        public readonly int                    $duplicatesRemoved,
        /** @var array<string, int> e.g. ['doi_match' => 12, 'title_fuzzy' => 5] */
        public readonly array                  $policyStats,
        public readonly int                    $durationMs,
    ) {}
}
