<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain\Port;

use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Search\Domain\ScholarlyWork;

interface DeduplicationPolicyPort
{
    public function name(): string;

    /**
     * Detect duplicates in the given work list.
     * Only return pairs not already confirmed by a higher-priority policy.
     * MUST NOT return duplicate entries for the same pair.
     *
     * @param  ScholarlyWork[] $works
     * @return Duplicate[]
     */
    public function detect(array $works): array;
}
