<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nexus\Search\Domain\Port\SearchCachePort;

/**
 * Laravel-backed implementation of SearchCachePort.
 *
 * Versioned key scheme: all data keys are prefixed with the current version.
 * invalidateAll() increments the version, making previous entries unreachable
 * without requiring tag support or a driver-level flush.
 */
class LaravelSearchCache implements SearchCachePort
{
    private const VERSION_KEY_SUFFIX = 'version';

    private ?int $version = null;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $keyPrefix = 'nexus:search:',
        private readonly int $versionTtlSeconds = 86_400 * 30,
    ) {}

    public function get(string $key): ?array
    {
        $payload = $this->cache->get($this->versioned($key));

        return is_array($payload) ? $payload : null;
    }

    public function put(string $key, array $results, int $ttlSeconds): void
    {
        $this->cache->put(
            $this->versioned($key),
            $results,
            $ttlSeconds
        );
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->versioned($key));
    }

    public function invalidateAll(): void
    {
        $newVersion = $this->currentVersion() + 1;
        $this->cache->put($this->versionKey(), $newVersion, $this->versionTtlSeconds);
        $this->version = $newVersion;
    }

    private function versioned(string $key): string
    {
        return $this->keyPrefix.'v'.$this->currentVersion().':'.$key;
    }

    private function currentVersion(): int
    {
        if ($this->version === null) {
            $this->version = $this->cache->get($this->versionKey(), 1);
        }

        return $this->version;
    }

    private function versionKey(): string
    {
        return $this->keyPrefix.self::VERSION_KEY_SUFFIX;
    }
}
