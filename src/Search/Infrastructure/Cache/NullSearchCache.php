<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Cache;

use Nexus\Search\Domain\Port\SearchCachePort;

/**
 * A no-op implementation of the SearchCachePort for environments without caching.
 */
final class NullSearchCache implements SearchCachePort
{
    public function get(string $key): ?array
    {
        return null;
    }

    public function put(string $key, array $works, int $ttlSeconds = 3600): void
    {
        // No-op
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function invalidateAll(): void
    {
        // No-op
    }
}
