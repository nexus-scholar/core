<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Application;

use GuzzleHttp\Promise\FulfilledPromise;
use Nexus\Search\Application\SearchAcrossProviders;
use Nexus\Search\Application\SearchAcrossProvidersHandler;
use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\Port\SearchCachePort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;

it('executes searches in parallel across providers', function () {
    $query = new SearchQuery(term: new SearchTerm('test'));
    
    $provider1 = \Mockery::mock(AcademicProviderPort::class);
    $provider1->shouldReceive('alias')->andReturn('p1');
    $provider1->shouldReceive('searchAsync')->once()->andReturn(new FulfilledPromise([]));
    
    $provider2 = \Mockery::mock(AcademicProviderPort::class);
    $provider2->shouldReceive('alias')->andReturn('p2');
    $provider2->shouldReceive('searchAsync')->once()->andReturn(new FulfilledPromise([]));
    
    $cache = \Mockery::mock(SearchCachePort::class);
    $cache->shouldReceive('get')->andReturn(null);
    $cache->shouldReceive('put')->once();
    
    $handler = new SearchAcrossProvidersHandler([$provider1, $provider2], $cache);
    
    $command = new SearchAcrossProviders($query);
    $result = $handler->handle($command);
    
    expect($result->providerResults)->toHaveCount(2);
    expect($result->fromCache)->toBeFalse();
});
