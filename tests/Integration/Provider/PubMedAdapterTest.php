<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Infrastructure\Provider\PubMedAdapter;
use Nexus\Search\Infrastructure\RateLimit\NullRateLimiter;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Tests\Support\CassetteHttpClient;

it('searches using esearch and efetch', function () {
    $config = ProviderConfigRegistry::defaults(pubmedApiKey: null)['pubmed'];
    $adapter = new PubMedAdapter(
        config: $config,
        http: new CassetteHttpClient('pubmed_search.yml'),
        rateLimiter: new NullRateLimiter,
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
    $config = ProviderConfigRegistry::defaults(pubmedApiKey: null)['pubmed'];
    $adapter = new PubMedAdapter(
        config: $config,
        http: new CassetteHttpClient('pubmed_fetch_by_id.yml'),
        rateLimiter: new NullRateLimiter,
    );

    // Some valid PMID
    $id = new WorkId(WorkIdNamespace::PUBMED, '36148332'); // A random highly cited paper or just some paper
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->title())->not->toBeEmpty();
});
