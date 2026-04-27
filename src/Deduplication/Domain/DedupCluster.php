<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain;

use Nexus\Deduplication\Domain\Port\RepresentativeElectionPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

/**
 * Aggregate root for a group of works determined to be duplicates of each other.
 *
 * Invariants:
 * - Always has at least one member (the seed)
 * - Representative is always a current member
 * - electRepresentative() must be called before treating output as final
 * - absorb() is idempotent for the same work (same primaryId)
 */
final class DedupCluster
{
    /** @var ScholarlyWork[] */
    private array $members = [];

    /** @var Duplicate[] */
    private array $duplicates = [];

    private ScholarlyWork $representative;

    private function __construct(
        public readonly DedupClusterId $id,
        ScholarlyWork $seed,
    ) {
        $this->members[]     = $seed;
        $this->representative = $seed;
    }

    public static function startWith(ScholarlyWork $seed): self
    {
        return new self(DedupClusterId::generate(), $seed);
    }

    public static function reconstitute(
        DedupClusterId $id,
        ScholarlyWork $representative,
        array $members,
        array $duplicates = []
    ): self {
        $cluster = new self($id, $representative);
        $cluster->members = $members;
        $cluster->duplicates = $duplicates;
        $cluster->representative = $representative;

        return $cluster;
    }

    /**
     * Add a duplicate work to this cluster with its evidence.
     * Idempotent: if a work with the same primary ID is already present, skip.
     */
    public function absorb(ScholarlyWork $work, Duplicate $evidence): void
    {
        $incomingKey = $work->primaryId()?->toString() ?? spl_object_hash($work);

        foreach ($this->members as $existing) {
            $existingKey = $existing->primaryId()?->toString() ?? spl_object_hash($existing);

            if ($existingKey === $incomingKey) {
                return; // already absorbed
            }
        }

        $this->members[]    = $work;
        $this->duplicates[] = $evidence;
    }

    public function representative(): ScholarlyWork
    {
        return $this->representative;
    }

    /**
     * Delegate representative selection to the election policy.
     * Replaces the current representative with the policy's choice.
     */
    public function electRepresentative(RepresentativeElectionPort $policy): void
    {
        $this->representative = $policy->elect($this->members);
    }

    /** @return ScholarlyWork[] — includes the representative */
    public function members(): array
    {
        return $this->members;
    }

    /** @return ScholarlyWork[] — excludes the representative */
    public function nonRepresentatives(): array
    {
        $repKey = $this->representative->primaryId()?->toString()
            ?? spl_object_hash($this->representative);

        return array_values(array_filter(
            $this->members,
            function (ScholarlyWork $m) use ($repKey): bool {
                $key = $m->primaryId()?->toString() ?? spl_object_hash($m);

                return $key !== $repKey;
            }
        ));
    }

    /** @return Duplicate[] */
    public function duplicateEvidence(): array
    {
        return $this->duplicates;
    }

    public function size(): int
    {
        return count($this->members);
    }

    public function hasDoi(): bool
    {
        return $this->representative->ids()->findByNamespace(WorkIdNamespace::DOI) !== null;
    }

    /** @return string[] */
    public function allDois(): array
    {
        $dois = [];

        foreach ($this->members as $work) {
            $doi = $work->ids()->findByNamespace(WorkIdNamespace::DOI);

            if ($doi !== null) {
                $dois[] = $doi->value;
            }
        }

        return array_unique($dois);
    }

    /** @return string[] */
    public function allArxivIds(): array
    {
        $ids = [];

        foreach ($this->members as $work) {
            $arxiv = $work->ids()->findByNamespace(WorkIdNamespace::ARXIV);

            if ($arxiv !== null) {
                $ids[] = $arxiv->value;
            }
        }

        return array_unique($ids);
    }

    /**
     * Count how many members came from each provider.
     * e.g. ['openalex' => 3, 'arxiv' => 1]
     *
     * @return array<string, int>
     */
    public function providerCounts(): array
    {
        $counts = [];

        foreach ($this->members as $work) {
            $provider = $work->sourceProvider();
            $counts[$provider] = ($counts[$provider] ?? 0) + 1;
        }

        return $counts;
    }
}
