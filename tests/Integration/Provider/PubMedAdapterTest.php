<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Infrastructure\Provider\PubMedAdapter;
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

it('searches using esearch and efetch', function () {
    VCR::insertCassette('pubmed_search.yml');

    $config = ProviderConfigRegistry::defaults(pubmedApiKey: null)['pubmed'];
    $adapter = new PubMedAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $query = new SearchQuery(
        term: new SearchTerm('CRISPR Cas9'),
        yearRange: new YearRange(2023, 2024),
        maxResults: 10,
    );

    $results = $adapter->search($query);

    expect($results)->not->toBeEmpty();
    
    $work = $results[0];
    expect($work->sourceProvider())->toBe('pubmed');
    expect($work->title())->not->toBeEmpty();
});

it('fetches a paper by PMID', function () {
    VCR::insertCassette('pubmed_fetch_by_id.yml');

    $config = ProviderConfigRegistry::defaults(pubmedApiKey: null)['pubmed'];
    $adapter = new PubMedAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    // Some valid PMID
    $id = new WorkId(WorkIdNamespace::PUBMED, '36148332'); // A random highly cited paper or just some paper
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->title())->not->toBeEmpty();
});
