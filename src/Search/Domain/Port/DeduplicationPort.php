<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

use Nexus\Search\Domain\CorpusSlice;

/**
 * Port for the deduplication bounded context.
 */
interface DeduplicationPort
{
    public function deduplicate(CorpusSlice $corpus): CorpusSlice;
}
