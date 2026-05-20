<?php

declare(strict_types=1);

namespace Tests\Integration\Provider;

use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Provider\IeeeAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;
use Nexus\Search\Infrastructure\RateLimit\NullRateLimiter;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Tests\Support\FakeHttpClient;

function ieeeArticleFixture(): array
{
    return [
        'doi' => '10.1109/Deep-ML.2019.00010',
        'title' => 'An Application of a Deep Learning Algorithm for Automatic Detection of Unexpected Accidents',
        'article_number' => '8876906',
        'publication_year' => 2019,
        'publication_title' => '2019 International Conference on Deep Learning and Machine Learning in Emerging Applications',
        'abstract' => 'This paper introduces an object detection and tracking system.',
        'citing_paper_count' => 70,
        'authors' => [
            'authors' => [
                ['full_name' => 'Kyu Beom Lee'],
                ['full_name' => 'Hyu Soung Shin'],
            ],
        ],
    ];
}

it('throws when no api key is provided', function () {
    $config = ProviderConfigRegistry::defaults(ieeeApiKey: null)['ieee'];
    $adapter = new IeeeAdapter(
        config: $config,
        http: new FakeHttpClient(),
        rateLimiter: new NullRateLimiter(),
    );

    $query = new SearchQuery(
        term: new SearchTerm('machine learning'),
        yearRange: new YearRange(2021, 2023),
        maxResults: 10,
    );

    expect(fn () => $adapter->search($query))->toThrow(\Nexus\Search\Domain\Exception\ProviderUnavailable::class);

    $id = new WorkId(WorkIdNamespace::DOI, '10.1109/TNNLS.2020.123456');
    expect(fn () => $adapter->fetchById($id))->toThrow(\Nexus\Search\Domain\Exception\ProviderUnavailable::class);
});

it('searches and fetches when api key is present', function () {
    $config = ProviderConfigRegistry::defaults(ieeeApiKey: 'dummy_key')['ieee'];
    $adapter = new IeeeAdapter(
        config: $config,
        http: new FakeHttpClient(new HttpResponse(200, ['articles' => [ieeeArticleFixture()]])),
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
    $config = ProviderConfigRegistry::defaults(ieeeApiKey: 'dummy_key')['ieee'];
    $adapter = new IeeeAdapter(
        config: $config,
        http: new FakeHttpClient(new HttpResponse(200, ['articles' => [ieeeArticleFixture()]])),
        rateLimiter: new NullRateLimiter(),
    );

    // Deep learning paper article number from previous search
    $id = new WorkId(WorkIdNamespace::IEEE, '8876906');
    $work = $adapter->fetchById($id);

    expect($work)->not->toBeNull();
    expect($work->sourceProvider())->toBe('ieee');
    expect($work->title())->not->toBeEmpty();
});
