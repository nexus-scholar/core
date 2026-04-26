<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Infrastructure;

/**
 * Union-Find (Disjoint Set Union) with path compression and union by rank.
 * Used by DeduplicateCorpusHandler to group transitively connected works.
 */
final class UnionFind
{
    /** @var array<string, string> */
    private array $parent = [];

    /** @var array<string, int> */
    private array $rank = [];

    public function makeSet(string $id): void
    {
        if (! array_key_exists($id, $this->parent)) {
            $this->parent[$id] = $id;
            $this->rank[$id]   = 0;
        }
    }

    /**
     * Find root with path compression (flattens tree on each call).
     */
    public function find(string $id): string
    {
        if ($this->parent[$id] !== $id) {
            $this->parent[$id] = $this->find($this->parent[$id]); // path compression
        }

        return $this->parent[$id];
    }

    /**
     * Union two sets by rank (attaches smaller tree under larger).
     */
    public function union(string $a, string $b): void
    {
        $rootA = $this->find($a);
        $rootB = $this->find($b);

        if ($rootA === $rootB) {
            return;
        }

        if ($this->rank[$rootA] < $this->rank[$rootB]) {
            $this->parent[$rootA] = $rootB;
        } elseif ($this->rank[$rootA] > $this->rank[$rootB]) {
            $this->parent[$rootB] = $rootA;
        } else {
            $this->parent[$rootB] = $rootA;
            $this->rank[$rootA]++;
        }
    }

    public function connected(string $a, string $b): bool
    {
        return $this->find($a) === $this->find($b);
    }

    /**
     * Return all groups as an array of arrays.
     * Each inner array contains the string IDs of a group.
     *
     * @return array<string, string[]>  root => members[]
     */
    public function groups(): array
    {
        $groups = [];

        foreach (array_keys($this->parent) as $id) {
            $root = $this->find($id);
            $groups[$root][] = $id;
        }

        return $groups;
    }

    /**
     * @return string[] all members of the same group as $id
     */
    public function groupOf(string $id): array
    {
        $root   = $this->find($id);
        $result = [];

        foreach (array_keys($this->parent) as $member) {
            if ($this->find($member) === $root) {
                $result[] = $member;
            }
        }

        return $result;
    }
}
