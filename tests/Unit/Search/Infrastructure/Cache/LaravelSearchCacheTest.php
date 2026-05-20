<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery;
use Nexus\Laravel\Persistence\LaravelSearchCache;

it('stores and retrieves normalized aggregate payloads with versioning', function () {
    $cache = Mockery::mock(CacheRepository::class);
    $payload = [
        'works' => [
            [
                'ids' => [['ns' => 'doi', 'val' => '10.1234/test']],
                'title' => 'Test Paper',
                'sourceProvider' => 'crossref',
            ],
        ],
        'stats' => [
            ['alias' => 'crossref', 'count' => 1, 'ms' => 25, 'error' => null],
        ],
        'total_raw' => 1,
    ];

    $cache->shouldReceive('get')
        ->with('nexus:search:version', 1)
        ->andReturn(1);

    $cache->shouldReceive('put')
        ->with(
            'nexus:search:v1:test_key',
            $payload,
            3600
        )
        ->once();

    $cache->shouldReceive('get')
        ->with('nexus:search:v1:test_key')
        ->andReturn($payload);

    $service = new LaravelSearchCache($cache);

    $service->put('test_key', $payload, 3600);
    $cached = $service->get('test_key');

    expect($cached)->toBe($payload);
});

it('invalidates all entries by bumping the version', function () {
    $cache = Mockery::mock(CacheRepository::class);

    // Initial version
    $cache->shouldReceive('get')
        ->with('nexus:search:version', 1)
        ->andReturn(1);

    // Bump version
    $cache->shouldReceive('put')
        ->with('nexus:search:version', 2, Mockery::any())
        ->once();

    $service = new LaravelSearchCache($cache);

    $service->invalidateAll();
});
