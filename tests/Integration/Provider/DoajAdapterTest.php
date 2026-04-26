<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\Provider\DoajAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Infrastructure\RateLimit\NullRateLimiter;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use VCR\VCR;

beforeEach(function () {
    VCR::configure()
        ->setCassettePath(__DIR__ . '/../../Fixture/vcr_cassettes')
        ->enableLibraryHooks(['curl', 'stream_wrapper']);
    VCR::turnOn();
});

afterEach(function () {
    VCR::eject();
    VCR::turnOff();
});

it('searches doaj with lucene year syntax', function () {
    VCR::insertCassette('doaj_search.yml');

    $config = ProviderConfigRegistry::defaults()['doaj'];
    $adapter = new DoajAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $query = new SearchQuery(
        term: new SearchTerm('open access'),
        yearRange: new YearRange(2021, 2023),
        maxResults: 10,
    );

    $results = $adapter->search($query);

    expect($results)->not->toBeEmpty();
    
    $work = $results[0];
    expect($work->sourceProvider())->toBe('doaj');
    expect($work->title())->not->toBeEmpty();
    expect($work->year())->toBeGreaterThanOrEqual(2021);
    expect($work->year())->toBeLessThanOrEqual(2023);
});

it('fetches a paper from doaj by DOI', function () {
    VCR::insertCassette('doaj_fetch_by_id.yml');

    $config = ProviderConfigRegistry::defaults()['doaj'];
    $adapter = new DoajAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    // Some DOAJ open access DOI that exists
    $id = new WorkId(WorkIdNamespace::DOI, '10.9767/bcrec.16.3.10313.588-600');
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->title())->not->toBeEmpty();
});
