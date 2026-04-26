<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\Provider\IeeeAdapter;
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

it('bails out when no api key is provided', function () {
    // VCR shouldn't even trigger since it bails early, but we wrap anyway
    VCR::insertCassette('ieee_no_key.yml');

    $config = ProviderConfigRegistry::defaults(ieeeApiKey: null)['ieee'];
    $adapter = new IeeeAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $query = new SearchQuery(
        term: new SearchTerm('machine learning'),
        yearRange: new YearRange(2021, 2023),
        maxResults: 10,
    );

    $results = $adapter->search($query);
    expect($results)->toBeEmpty();

    $id = new WorkId(WorkIdNamespace::DOI, '10.1109/TNNLS.2020.123456');
    $work = $adapter->fetchById($id);
    expect($work)->toBeNull();
});

it('searches and fetches when api key is present', function () {
    // Note: To successfully record this cassette, a real IEEE API key must
    // be temporarily provided during the first run.
    // If not, IEEE will return 401 or 403, and the adapter will return [] / null.
    // We will test that it correctly returns empty if unauthorized.
    VCR::insertCassette('ieee_with_key.yml');

    $config = ProviderConfigRegistry::defaults(ieeeApiKey: 'dummy_key')['ieee'];
    $adapter = new IeeeAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $query = new SearchQuery(
        term: new SearchTerm('deep learning'),
        maxResults: 5,
    );

    $results = $adapter->search($query);
    
    expect($results)->not->toBeEmpty();
    $work = $results[0];
    expect($work->sourceProvider())->toBe('ieee');
    expect($work->title())->not->toBeEmpty();
});

it('fetches a paper from ieee by article number', function () {
    VCR::insertCassette('ieee_fetch_by_id.yml');

    $config = ProviderConfigRegistry::defaults(ieeeApiKey: 'dummy_key')['ieee'];
    $adapter = new IeeeAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    // Deep learning paper article number from previous search
    $id = new WorkId(WorkIdNamespace::IEEE, '8876906');
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->sourceProvider())->toBe('ieee');
    expect($work->title())->not->toBeEmpty();
});
