<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\Provider\OpenAlexAdapter;
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

it('searches using works endpoint', function () {
    VCR::insertCassette('openalex_search.yml');

    $config = ProviderConfigRegistry::defaults(mailTo: 'test@example.com')['openalex'];
    $adapter = new OpenAlexAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $query = new SearchQuery(
        term: new SearchTerm('artificial intelligence'),
        yearRange: new YearRange(2021, 2023),
        maxResults: 5,
    );

    $results = $adapter->search($query);

    expect($results)->not->toBeEmpty();
    expect($results)->toHaveCount(5);

    $work = $results[0];
    expect($work->sourceProvider())->toBe('openalex');
    expect($work->title())->not->toBeEmpty();
    expect($work->year())->toBeGreaterThanOrEqual(2021);
    expect($work->year())->toBeLessThanOrEqual(2023);

    // OpenAlex should return its native ID
    expect($work->ids()->findByNamespace(WorkIdNamespace::OPENALEX))->not->toBeNull();
});

it('fetches a paper by OpenAlex ID', function () {
    VCR::insertCassette('openalex_fetch_by_id.yml');

    $config = ProviderConfigRegistry::defaults(mailTo: 'test@example.com')['openalex'];
    $adapter = new OpenAlexAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    // A well-known OpenAlex Work ID (Piwowar et al. 2018 OA study)
    $id = new WorkId(WorkIdNamespace::OPENALEX, 'w2741809807');
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->sourceProvider())->toBe('openalex');
    expect($work->title())->not->toBeEmpty();
    // The ID is returned in the response body and normalized by WorkId
    expect($work->ids()->findByNamespace(WorkIdNamespace::OPENALEX))->not->toBeNull();
});
