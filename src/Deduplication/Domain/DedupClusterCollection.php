<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain;

use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;

/**
 * A collection of deduplicated clusters.
 */
final class DedupClusterCollection
{
    /** @var DedupCluster[] */
    private array $clusters = [];

    public function __construct(DedupCluster ...$clusters)
    {
        $this->clusters = array_values($clusters);
    }

    public static function empty(): self
    {
        return new self;
    }

    public function add(DedupCluster $cluster): void
    {
        $this->clusters[] = $cluster;
    }

    public function count(): int
    {
        return count($this->clusters);
    }

    public function totalMemberCount(): int
    {
        return array_sum(array_map(fn (DedupCluster $c) => $c->size(), $this->clusters));
    }

    public function duplicateCount(): int
    {
        return $this->totalMemberCount() - $this->count();
    }

    /** @return DedupCluster[] */
    public function all(): array
    {
        return $this->clusters;
    }

    /**
     * Returns a CorpusSlice containing only the representative of each cluster.
     */
    public function toCorpusSlice(): CorpusSlice
    {
        $reps = array_map(fn (DedupCluster $c) => $this->mergedRepresentative($c), $this->clusters);

        return CorpusSlice::fromWorks(...$reps);
    }

    private function mergedRepresentative(DedupCluster $cluster): ?ScholarlyWork
    {
        $representative = $cluster->representative();

        if ($representative === null) {
            return null;
        }

        foreach ($cluster->members() as $member) {
            if ($member === $representative) {
                continue;
            }

            $representative = $representative->mergeWith($member);
        }

        return $representative;
    }

    /**
     * Find the cluster that contains a work with the given ID (any member, any namespace).
     */
    public function findByWorkId(WorkId $id): ?DedupCluster
    {
        foreach ($this->clusters as $cluster) {
            foreach ($cluster->members() as $member) {
                foreach ($member->ids()->all() as $workId) {
                    if ($workId->equals($id)) {
                        return $cluster;
                    }
                }
            }
        }

        return null;
    }
}
