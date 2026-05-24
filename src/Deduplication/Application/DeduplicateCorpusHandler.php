<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\DedupClusterCollection;
use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Deduplication\Domain\Port\DeduplicationPolicyPort;
use Nexus\Deduplication\Domain\Port\RepresentativeElectionPort;
use Nexus\Deduplication\Infrastructure\UnionFind;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\ValueObject\CorpusOperation;

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
        private readonly array $policies,
        private readonly RepresentativeElectionPort $electionPolicy,
        private readonly ?CorpusLockPolicy $lockPolicy = null,
    ) {}

    public function handle(DeduplicateCorpus $command): DeduplicateCorpusResult
    {
        $this->lockPolicy?->assertCorpusMutable($command->projectId, CorpusOperation::DEDUPLICATE);

        $startNs = hrtime(true);

        $works = $command->corpus->all();
        $inputCount = count($works);

        if ($inputCount === 0) {
            return new DeduplicateCorpusResult(
                clusters: DedupClusterCollection::empty(),
                inputCount: 0,
                uniqueCount: 0,
                duplicatesRemoved: 0,
                policyStats: [],
                durationMs: 0,
            );
        }

        // Resolve policies to use
        $policies = $this->resolvePolicies($command->policyAliases);

        // Build a key→work map and initialise UnionFind
        $uf = new UnionFind;
        $keyMap = []; // key => ScholarlyWork

        foreach ($works as $work) {
            $key = $work->primaryId()?->toString() ?? spl_object_hash($work);
            $keyMap[$key] = $work;
            $uf->makeSet($key);
        }

        // Run policies
        $policyStats = [];
        $evidenceByPair = []; // canonical "primaryKey|secondaryKey" => Duplicate
        $evidenceGraph = []; // primaryKey => [secondaryKey => Duplicate]

        foreach ($policies as $policy) {
            $found = $policy->detect($works);
            $count = 0;

            foreach ($found as $duplicate) {
                $primaryKey = $duplicate->primaryId->toString();
                $secondaryKey = $duplicate->secondaryId->toString();
                if (! isset($keyMap[$primaryKey]) || ! isset($keyMap[$secondaryKey])) {
                    continue;
                }

                $pairKey = $this->pairKey($primaryKey, $secondaryKey);

                // Skip already-paired works (from higher-priority policies)
                if (isset($evidenceByPair[$pairKey])) {
                    continue;
                }

                $uf->union($primaryKey, $secondaryKey);
                $evidenceByPair[$pairKey] = $duplicate;
                $evidenceGraph[$primaryKey][$secondaryKey] = $duplicate;
                $evidenceGraph[$secondaryKey][$primaryKey] = $duplicate;
                $count++;
            }

            $policyStats[$policy->name()] = $count;
        }

        // Extract groups and build clusters
        $groups = $uf->groups();
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

            // Re-absorb members by walking the stored pair evidence that created the group.
            foreach ($this->evidenceForGroup($memberKeys, $evidenceGraph) as [$memberKey, $evidence]) {
                if (! isset($keyMap[$memberKey])) {
                    continue;
                }

                $cluster->absorb($keyMap[$memberKey], $evidence);
            }

            $cluster->electRepresentative($this->electionPolicy);
            $collection->add($cluster);
        }

        $uniqueCount = $collection->count();
        $duplicatesRemoved = $inputCount - $uniqueCount;
        $durationMs = (int) round((hrtime(true) - $startNs) / 1_000_000);

        return new DeduplicateCorpusResult(
            clusters: $collection,
            inputCount: $inputCount,
            uniqueCount: $uniqueCount,
            duplicatesRemoved: $duplicatesRemoved,
            policyStats: $policyStats,
            durationMs: $durationMs,
        );
    }

    /**
     * @param  string[]  $aliases  empty = all
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

    private function pairKey(string $firstKey, string $secondKey): string
    {
        if ($firstKey > $secondKey) {
            [$firstKey, $secondKey] = [$secondKey, $firstKey];
        }

        return $firstKey.'|'.$secondKey;
    }

    /**
     * @param  string[]  $memberKeys
     * @param  array<string, array<string, Duplicate>>  $evidenceGraph
     * @return array<int, array{0: string, 1: Duplicate}>
     */
    private function evidenceForGroup(array $memberKeys, array $evidenceGraph): array
    {
        if (count($memberKeys) < 2) {
            return [];
        }

        $memberLookup = array_fill_keys($memberKeys, true);
        $absorbed = [$memberKeys[0] => true];
        $queue = [$memberKeys[0]];
        $absorptions = [];

        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            $fromKey = $queue[$cursor];

            foreach ($evidenceGraph[$fromKey] ?? [] as $toKey => $evidence) {
                if (! isset($memberLookup[$toKey]) || isset($absorbed[$toKey])) {
                    continue;
                }

                $absorbed[$toKey] = true;
                $queue[] = $toKey;
                $absorptions[] = [$toKey, $evidence];
            }
        }

        if (count($absorbed) !== count($memberKeys)) {
            throw new \LogicException('Cannot assemble dedup cluster because recorded evidence does not connect every member.');
        }

        return $absorptions;
    }
}
