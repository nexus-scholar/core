<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Infrastructure;

use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\Port\RepresentativeElectionPort;
use Nexus\Search\Domain\ScholarlyWork;

/**
 * Produces a single merged ScholarlyWork from a DedupCluster.
 *
 * Strategy:
 *   1. Elect the representative via the injected policy.
 *   2. Merge all other members into the representative
 *      using ScholarlyWork::mergeWith() — never overwrites existing fields.
 */
final class WorkFuser
{
    public function __construct(
        private readonly RepresentativeElectionPort $electionPolicy,
    ) {}

    public function fuse(DedupCluster $cluster): ScholarlyWork
    {
        $cluster->electRepresentative($this->electionPolicy);
        $representative = $cluster->representative();

        foreach ($cluster->nonRepresentatives() as $member) {
            $representative = $representative->mergeWith($member);
        }

        return $representative;
    }
}
