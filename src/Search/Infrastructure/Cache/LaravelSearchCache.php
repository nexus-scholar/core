<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Cache;

use Nexus\Search\Domain\Port\SearchCachePort;
use Nexus\Search\Domain\ScholarlyWork;

/**
 * Cache implementation that uses a version counter to invalidate all entries.
 *
 * invalidateAll() increments the version stored in the cache.
 * All keys are prefixed with the current version, so incrementing it
 * effectively expires all previously cached entries without tag-flushing.
 *
 * This design avoids the old package's silent no-op bug (known bug #15)
 * where cache()->tags()->flush() did nothing on stores that don't support tags.
 */
final class LaravelSearchCache implements SearchCachePort
{
    private ?int $version = null;

    public function __construct(
        private readonly \Illuminate\Contracts\Cache\Repository $cache,
        private readonly string $keyPrefix = 'nexus:search:',
    ) {}

    public function get(string $key): ?array
    {
        return $this->cache->get($this->versioned($key));
    }

    public function put(string $key, array $results, int $ttlSeconds): void
    {
        $this->cache->put($this->versioned($key), $results, $ttlSeconds);
    }

    public function invalidateAll(): void
    {
        $versionKey = $this->keyPrefix . 'version';
        $current    = (int) $this->cache->get($versionKey, 0);
        $this->cache->forever($versionKey, $current + 1);
        $this->version = null; // reset local cache of version
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->versioned($key));
    }

    private function versioned(string $key): string
    {
        if ($this->version === null) {
            $versionKey    = $this->keyPrefix . 'version';
            $this->version = (int) $this->cache->get($versionKey, 0);
        }

        return "{$this->keyPrefix}v{$this->version}:{$key}";
    }
}
