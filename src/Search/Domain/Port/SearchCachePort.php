<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

interface SearchCachePort
{
    /**
     * Retrieve previously cached search payload for this key.
     * Returns null on cache miss.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * Store a normalized search payload in the cache.
     *
     * @param  array<string, mixed>  $results
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
