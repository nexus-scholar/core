<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain;

use Nexus\Search\Domain\CorpusSlice;
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
        return new self();
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
    public function representativeCorpus(): CorpusSlice
    {
        $slice = CorpusSlice::empty();

        foreach ($this->clusters as $cluster) {
            $slice->addWork($cluster->representative());
        }

        return $slice;
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
