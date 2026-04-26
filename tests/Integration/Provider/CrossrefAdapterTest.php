<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;
use Nexus\Search\Infrastructure\Provider\CrossrefAdapter;
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

it('searches using crossref api', function () {
    VCR::insertCassette('crossref_search.yml');

    $config = ProviderConfigRegistry::defaults(mailTo: 'test@example.com')['crossref'];
    $adapter = new CrossrefAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $query = new SearchQuery(
        term: new SearchTerm('artificial intelligence'),
        yearRange: new YearRange(2022, 2023),
        maxResults: 5,
    );

    $results = $adapter->search($query);

    expect($results)->not->toBeEmpty();
    expect($results)->toHaveCount(5);

    $work = $results[0];
    expect($work->sourceProvider())->toBe('crossref');
    expect($work->title())->not->toBeEmpty();
    expect($work->year())->toBeGreaterThanOrEqual(2022);
    expect($work->year())->toBeLessThanOrEqual(2023);

    expect($work->ids()->findByNamespace(WorkIdNamespace::DOI))->not->toBeNull();
});

it('fetches a paper by DOI', function () {
    VCR::insertCassette('crossref_fetch_by_id.yml');

    $config = ProviderConfigRegistry::defaults(mailTo: 'test@example.com')['crossref'];
    $adapter = new CrossrefAdapter(
        config: $config,
        http: GuzzleHttpClient::create(),
        rateLimiter: new NullRateLimiter(),
    );

    $id = new WorkId(WorkIdNamespace::DOI, '10.1109/5.771073');
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->sourceProvider())->toBe('crossref');
    expect($work->title())->not->toBeEmpty();
    expect($work->ids()->findByNamespace(WorkIdNamespace::DOI))->not->toBeNull();
});
