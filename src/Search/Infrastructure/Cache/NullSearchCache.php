<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Cache;

use Nexus\Search\Domain\Port\SearchCachePort;

/**
 * No-op cache implementation — always misses.
 * Use in: tests, standalone usage (without Laravel), dev mode.
 */
final class NullSearchCache implements SearchCachePort
{
    public function get(string $key): ?array
    {
        return null;
    }

    public function put(string $key, array $results, int $ttlSeconds): void
    {
        // intentional no-op
    }

    public function invalidateAll(): void
    {
        // intentional no-op
    }

    public function has(string $key): bool
    {
        return false;
    }
}
