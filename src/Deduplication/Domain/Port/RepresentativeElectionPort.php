<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain\Port;

use Nexus\Search\Domain\ScholarlyWork;

interface RepresentativeElectionPort
{
    /**
     * Given a list of members in a cluster, return the best representative.
     *
     * @param  ScholarlyWork[] $members
     */
    public function elect(array $members): ScholarlyWork;
}
