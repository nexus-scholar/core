<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Provider\DoajAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;

it('escapes_lucene_special_characters_in_search_term', function (): void {
    $http = \Mockery::mock(HttpClientPort::class);
    
    // We expect the HTTP client to be called with the escaped query
    $http->shouldReceive('get')->once()->withArgs(function($url, $query_params, $headers) {
        $decoded = urldecode($url);
        return str_contains($decoded, 'COVID\-19 \(Review\)\: A state\-of\-the\-art');
    })->andReturn(new HttpResponse(200, ['results' => []]));

    $rateLimiter = \Mockery::mock(RateLimiterPort::class);
    $rateLimiter->shouldReceive('waitForToken')->once();

    $config = new ProviderConfig('doaj', 'http://doaj.org', 10.0);

    $adapter = new DoajAdapter($http, $rateLimiter, $config);

    $query = new SearchQuery(
        term: new SearchTerm('COVID-19 (Review): A state-of-the-art'),
        yearRange: new YearRange(2020, 2021)
    );

    $adapter->search($query);
});
