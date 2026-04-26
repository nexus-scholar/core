<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;
use Nexus\Search\Infrastructure\Provider\SemanticScholarAdapter;
use Nexus\Search\Domain\Exception\ProviderUnavailable;

it('returns_partial_results_if_subsequent_page_fails', function (): void {
    $http = \Mockery::mock(HttpClientPort::class);
    
    // Page 1 succeeds
    $http->shouldReceive('get')->once()->withArgs(function($url, $params) {
        return !isset($params['token']);
    })->andReturn(new HttpResponse(200, [
        'data' => [['paperId' => '1', 'title' => 'Page 1']],
        'token' => 'page2token'
    ]));

    // Page 2 fails
    $http->shouldReceive('get')->once()->withArgs(function($url, $params) {
        return isset($params['token']) && $params['token'] === 'page2token';
    })->andThrow(new ProviderUnavailable('semantic_scholar', 'Network error'));

    $rateLimiter = \Mockery::mock(RateLimiterPort::class);
    $rateLimiter->shouldReceive('waitForToken')->twice();

    $config = new ProviderConfig('semantic_scholar', 'http://s2.org', 10.0, maxRetries: 1);

    $adapter = new SemanticScholarAdapter($http, $rateLimiter, $config);

    // We expect it to swallow the exception on page 2 and return page 1's results
    $query = new SearchQuery(new SearchTerm('test'), maxResults: 100);
    $results = $adapter->search($query);

    expect($results)->toHaveCount(1);
    expect($results[0]->title())->toBe('Page 1');
});
