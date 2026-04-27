<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\DedupClusterCollection;
use Nexus\Deduplication\Domain\Port\DeduplicationPolicyPort;
use Nexus\Deduplication\Domain\Port\RepresentativeElectionPort;
use Nexus\Deduplication\Infrastructure\UnionFind;
use Nexus\Search\Domain\ScholarlyWork;

/**
 * Orchestrates the full deduplication pipeline.
 *
 * Algorithm:
 *   1. Initialize UnionFind with all work primary IDs
 *   2. For each policy (in order): detect duplicates → union pairs in UnionFind
 *      (exact-match policies MUST be registered before fuzzy ones)
 *   3. Extract groups from UnionFind
 *   4. For each group: create DedupCluster, absorb members, elect representative
 *   5. Return DedupClusterCollection + stats
 */
final class DeduplicateCorpusHandler
{
    public function __construct(
        /** @var DeduplicationPolicyPort[] — ordered: exact-match first, fuzzy last */
        private readonly array                    $policies,
        private readonly RepresentativeElectionPort $electionPolicy,
    ) {}

    public function handle(DeduplicateCorpus $command): DeduplicateCorpusResult
    {
        $startNs = hrtime(true);

        $works = $command->corpus->all();
        $inputCount = count($works);

        if ($inputCount === 0) {
            return new DeduplicateCorpusResult(
                clusters:          DedupClusterCollection::empty(),
                inputCount:        0,
                uniqueCount:       0,
                duplicatesRemoved: 0,
                policyStats:       [],
                durationMs:        0,
            );
        }

        // Resolve policies to use
        $policies = $this->resolvePolicies($command->policyAliases);

        // Build a key→work map and initialise UnionFind
        $uf      = new UnionFind();
        $keyMap  = []; // key => ScholarlyWork

        foreach ($works as $work) {
            $key = $work->primaryId()?->toString() ?? spl_object_hash($work);
            $keyMap[$key] = $work;
            $uf->makeSet($key);
        }

        // Run policies
        $policyStats = [];
        $duplicatesByPair = []; // "primaryKey|secondaryKey" => true  (deduplication guard)

        foreach ($policies as $policy) {
            $found = $policy->detect($works);
            $count = 0;

            foreach ($found as $duplicate) {
                $primaryKey   = $duplicate->primaryId->toString();
                $secondaryKey = $duplicate->secondaryId->toString();
                $pairKey      = $primaryKey . '|' . $secondaryKey;
                $pairKeyRev   = $secondaryKey . '|' . $primaryKey;

                // Skip already-paired works (from higher-priority policies)
                if (isset($duplicatesByPair[$pairKey]) || isset($duplicatesByPair[$pairKeyRev])) {
                    continue;
                }

                if (! isset($keyMap[$primaryKey]) || ! isset($keyMap[$secondaryKey])) {
                    continue;
                }

                $uf->union($primaryKey, $secondaryKey);
                $duplicatesByPair[$pairKey] = true;
                $count++;
            }

            $policyStats[$policy->name()] = $count;
        }

        // Extract groups and build clusters
        $groups     = $uf->groups();
        $collection = DedupClusterCollection::empty();

        foreach ($groups as $memberKeys) {
            if ($memberKeys === []) {
                continue;
            }

            $seedKey = $memberKeys[0];

            if (! isset($keyMap[$seedKey])) {
                continue;
            }

            $cluster = DedupCluster::startWith($keyMap[$seedKey], $command->projectId);

            // Re-absorb with evidence for each non-seed member
            foreach (array_slice($memberKeys, 1) as $memberKey) {
                if (! isset($keyMap[$memberKey])) {
                    continue;
                }

                // Find the Duplicate evidence for this pair (best-effort)
                $evidence = $this->findEvidence($keyMap[$seedKey], $keyMap[$memberKey], $policies, $works);
                $cluster->absorb($keyMap[$memberKey], $evidence);
            }

            $cluster->electRepresentative($this->electionPolicy);
            $collection->add($cluster);
        }

        $uniqueCount       = $collection->count();
        $duplicatesRemoved = $inputCount - $uniqueCount;
        $durationMs        = (int) round((hrtime(true) - $startNs) / 1_000_000);

        return new DeduplicateCorpusResult(
            clusters:          $collection,
            inputCount:        $inputCount,
            uniqueCount:       $uniqueCount,
            duplicatesRemoved: $duplicatesRemoved,
            policyStats:       $policyStats,
            durationMs:        $durationMs,
        );
    }

    /**
     * Try to recover the Duplicate evidence for a pair from already-run policies.
     * Falls back to a synthetic fingerprint Duplicate if not found.
     */
    private function findEvidence(
        ScholarlyWork $primary,
        ScholarlyWork $secondary,
        array $policies,
        array $works,
    ): \Nexus\Deduplication\Domain\Duplicate {
        foreach ($policies as $policy) {
            $found = $policy->detect([$primary, $secondary]);

            foreach ($found as $dup) {
                if (($dup->primaryId->equals($primary->primaryId() ?? $dup->primaryId))
                    || ($dup->secondaryId->equals($secondary->primaryId() ?? $dup->secondaryId))
                ) {
                    return $dup;
                }
            }
        }

        // Synthetic fallback
        return new \Nexus\Deduplication\Domain\Duplicate(
            primaryId:   $primary->primaryId() ?? $secondary->primaryId(),
            secondaryId: $secondary->primaryId() ?? $primary->primaryId(),
            reason:      \Nexus\Deduplication\Domain\DuplicateReason::FINGERPRINT,
            confidence:  0.85,
        );
    }

    /**
     * @param string[] $aliases empty = all
     * @return DeduplicationPolicyPort[]
     */
    private function resolvePolicies(array $aliases): array
    {
        if ($aliases === []) {
            return $this->policies;
        }

        return array_values(array_filter(
            $this->policies,
            fn (DeduplicationPolicyPort $p) => in_array($p->name(), $aliases, true),
        ));
    }
}
