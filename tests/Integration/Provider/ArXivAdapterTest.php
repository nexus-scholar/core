<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\Provider\ArXivAdapter;
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

it('searches using arxiv api', function () {
    VCR::insertCassette('arxiv_search.yml');

    $config = ProviderConfigRegistry::defaults()['arxiv'];
    $adapter = new ArXivAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
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
});

it('fetches a paper by arXiv ID', function () {
    VCR::insertCassette('arxiv_fetch_by_id.yml');

    $config = ProviderConfigRegistry::defaults()['arxiv'];
    $adapter = new ArXivAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $id = new WorkId(WorkIdNamespace::ARXIV, '2201.11903');
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->sourceProvider())->toBe('arxiv');
    expect($work->title())->not->toBeEmpty();
    expect($work->ids()->findByNamespace(WorkIdNamespace::ARXIV))->not->toBeNull();
});
