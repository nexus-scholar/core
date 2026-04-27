<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Infrastructure\Cache\LaravelSearchCache;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('stores and retrieves serialized results with versioning', function () {
    $cache = Mockery::mock(CacheRepository::class);
    $results = [
        ScholarlyWork::reconstitute(
            ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1234/test')),
            title: 'Test Paper',
            sourceProvider: 'crossref'
        )
    ];

    // Version retrieval
    $cache->shouldReceive('get')
        ->with('nexus:search:version', 1)
        ->andReturn(1);

    // Store operation
    $cache->shouldReceive('put')
        ->with(
            'nexus:search:v1:test_key',
            Mockery::on(function ($data) {
                return count($data) === 1 && $data[0]['title'] === 'Test Paper';
            }),
            3600
        )
        ->once();

    // Fetch operation
    $cache->shouldReceive('get')
        ->with('nexus:search:v1:test_key')
        ->andReturn([
            [
                'ids' => [['namespace' => 'doi', 'value' => '10.1234/test']],
                'title' => 'Test Paper',
                'sourceProvider' => 'crossref',
                'year' => null,
                'abstract' => null,
                'citedByCount' => null,
                'isRetracted' => false,
            ]
        ]);

    $service = new LaravelSearchCache($cache);

    $service->put('test_key', $results, 3600);
    $cached = $service->get('test_key');

    expect($cached)->toHaveCount(1);
    expect($cached[0]->title())->toBe('Test Paper');
    expect($cached[0]->ids()->all()[0]->value)->toBe('10.1234/test');
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
