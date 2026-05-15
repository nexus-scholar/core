<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nexus\Search\Domain\Port\SearchCachePort;
use Nexus\Search\Domain\ScholarlyWork;

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

    /**
     * @return ScholarlyWork[]|null
     */
    public function get(string $key): ?array
    {
        $serialized = $this->cache->get($this->versioned($key));

        if ($serialized === null) {
            return null;
        }

        return $this->deserialize($serialized);
    }

    /**
     * @param ScholarlyWork[] $results
     */
    public function put(string $key, array $results, int $ttlSeconds): void
    {
        $this->cache->put(
            $this->versioned($key),
            $this->serialize($results),
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
        return $this->keyPrefix . 'v' . $this->currentVersion() . ':' . $key;
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
        return $this->keyPrefix . self::VERSION_KEY_SUFFIX;
    }

    /**
     * @param ScholarlyWork[] $works
     * @return array<int, array<string, mixed>>
     */
    private function serialize(array $works): array
    {
        return array_map(fn (ScholarlyWork $work) => [
            'ids' => array_map(
                fn ($id) => ['namespace' => $id->namespace->value, 'value' => $id->value],
                $work->ids()->all()
            ),
            'title' => $work->title(),
            'year' => $work->year(),
            'abstract' => $work->abstract(),
            'citedByCount' => $work->citedByCount(),
            'isRetracted' => $work->isRetracted(),
            'sourceProvider' => $work->sourceProvider(),
        ], $works);
    }

    /**
     * @param array<int, array<string, mixed>> $serialized
     * @return ScholarlyWork[]
     */
    private function deserialize(array $serialized): array
    {
        return array_map(function (array $data): ScholarlyWork {
            $idSet = new \Nexus\Shared\ValueObject\WorkIdSet(
                ...array_map(
                    fn (array $id) => new \Nexus\Shared\ValueObject\WorkId(
                        \Nexus\Shared\ValueObject\WorkIdNamespace::from($id['namespace']),
                        $id['value']
                    ),
                    $data['ids']
                )
            );

            return ScholarlyWork::reconstitute(
                ids: $idSet,
                title: $data['title'],
                sourceProvider: $data['sourceProvider'],
                year: $data['year'] ?? null,
                abstract: $data['abstract'] ?? null,
                citedByCount: $data['citedByCount'] ?? null,
                isRetracted: $data['isRetracted'] ?? false,
            );
        }, $serialized);
    }
}
