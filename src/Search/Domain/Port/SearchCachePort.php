<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

use Nexus\Search\Domain\ScholarlyWork;

interface SearchCachePort
{
    /**
     * Retrieve previously cached results for this key.
     * Returns null on cache miss.
     *
     * @return ScholarlyWork[]|null
     */
    public function get(string $key): ?array;

    /**
     * Store results in the cache.
     *
     * @param ScholarlyWork[] $results
     */
    public function put(string $key, array $results, int $ttlSeconds): void;

    /**
     * Invalidate all cache entries by bumping a global version counter.
     * MUST NOT rely on tag flushing (the old package's tag flush was a no-op).
     * Implementation: store a version integer, prefix all keys with it.
     */
    public function invalidateAll(): void;

    /** Check existence without fetching the value. */
    public function has(string $key): bool;
}
