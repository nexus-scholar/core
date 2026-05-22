<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Infrastructure\Provider\ArXivAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Infrastructure\RateLimit\NullRateLimiter;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\FakeHttpClient;

function arxivCassetteBody(string $cassette): string
{
    $records = Yaml::parseFile(__DIR__.'/../../Fixture/vcr_cassettes/'.$cassette);

    return (string) ($records[0]['response']['body'] ?? '');
}

it('searches using arxiv api', function () {
    $http = new FakeHttpClient(new HttpResponse(
        statusCode: 200,
        body: [],
        rawBody: arxivCassetteBody('arxiv_search.yml'),
    ));

    $config = ProviderConfigRegistry::defaults()['arxiv'];
    $adapter = new ArXivAdapter(
        config: $config,
        http: $http,
        rateLimiter: new NullRateLimiter,
    );

    $query = new SearchQuery(
        term: new SearchTerm('electron'),
        yearRange: null, // Arxiv doesn't support easy year filtering in this basic way
        maxResults: 5,
    );

    $results = $adapter->search($query);

    expect($results)->not->toBeEmpty();
    expect($results)->toHaveCount(5);

    $work = $results[0];
    expect($work->sourceProvider())->toBe('arxiv');
    expect($work->title())->not->toBeEmpty();

    expect($work->ids()->findByNamespace(WorkIdNamespace::ARXIV))->not->toBeNull();
    expect($http->calls[0]['query'])->toMatchArray([
        'search_query' => 'all:electron',
        'start' => 0,
        'max_results' => 5,
    ]);
});

it('fetches a paper by arXiv ID', function () {
    $http = new FakeHttpClient(new HttpResponse(
        statusCode: 200,
        body: [],
        rawBody: arxivCassetteBody('arxiv_fetch_by_id.yml'),
    ));

    $config = ProviderConfigRegistry::defaults()['arxiv'];
    $adapter = new ArXivAdapter(
        config: $config,
        http: $http,
        rateLimiter: new NullRateLimiter,
    );

    $id = new WorkId(WorkIdNamespace::ARXIV, '2201.11903');
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->sourceProvider())->toBe('arxiv');
    expect($work->title())->not->toBeEmpty();
    expect($work->ids()->findByNamespace(WorkIdNamespace::ARXIV))->not->toBeNull();
    expect($http->calls[0]['query'])->toBe(['id_list' => '2201.11903']);
});
