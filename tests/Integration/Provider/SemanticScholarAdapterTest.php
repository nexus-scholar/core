<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Infrastructure\Provider\SemanticScholarAdapter;
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

it('searches using the bulk endpoint with continuation tokens', function () {
    VCR::insertCassette('s2_bulk_search.yml');

    $config = ProviderConfigRegistry::defaults(s2ApiKey: null)['semantic_scholar'];
    
    $adapter = new SemanticScholarAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    // Provide a query that translates boolean syntax
    $query = new SearchQuery(
        term: new SearchTerm('CRISPR AND Cas9'),
        yearRange: new YearRange(2020, 2024),
        maxResults: 15,
    );

    $results = $adapter->search($query);

    expect($results)->not->toBeEmpty();
    
    $work = $results[0];
    expect($work->sourceProvider())->toBe('semantic_scholar');
    expect($work->title())->not->toBeEmpty();
    expect($work->year())->toBeGreaterThanOrEqual(2020);
});

it('fetches a paper by DOIs and S2 IDs', function () {
    VCR::insertCassette('s2_fetch_by_id.yml');

    $config = ProviderConfigRegistry::defaults(s2ApiKey: null)['semantic_scholar'];
    $adapter = new SemanticScholarAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $id = new WorkId(WorkIdNamespace::DOI, '10.1038/nature11409');
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->title())->toContain('hydrogel');
    expect($work->authors()->isEmpty())->toBeFalse();
});

it('paginates using continuation tokens', function () {
    VCR::insertCassette('s2_pagination.yml');

    $config = ProviderConfigRegistry::defaults(s2ApiKey: null)['semantic_scholar'];
    $adapter = new SemanticScholarAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    // Request 4 items. The cassette will return 2 items per page.
    $query = new SearchQuery(
        term: new SearchTerm('pagination test'),
        yearRange: null,
        maxResults: 4,
    );

    $results = $adapter->search($query);

    // We should have fetched 4 items across 2 pages
    expect($results)->toHaveCount(4);
});
