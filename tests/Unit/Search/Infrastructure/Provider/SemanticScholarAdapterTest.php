<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;
use Nexus\Search\Infrastructure\Provider\SemanticScholarAdapter;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('returns_partial_results_if_subsequent_page_fails', function (): void {
    $http = \Mockery::mock(HttpClientPort::class);

    // Page 1 succeeds
    $http->shouldReceive('get')->once()->withArgs(function ($url, $params) {
        return ! isset($params['token']);
    })->andReturn(new HttpResponse(200, [
        'data' => [['paperId' => '1', 'title' => 'Page 1']],
        'token' => 'page2token',
    ]));

    // Page 2 fails
    $http->shouldReceive('get')->once()->withArgs(function ($url, $params) {
        return isset($params['token']) && $params['token'] === 'page2token';
    })->andThrow(new ProviderUnavailable('semantic_scholar', 'Network error'));

    $rateLimiter = \Mockery::mock(RateLimiterPort::class);
    $rateLimiter->shouldReceive('waitForToken')->twice();

    $config = new ProviderConfig('semantic_scholar', 'http://s2.org', 10.0, maxRetries: 1);

    $adapter = new SemanticScholarAdapter($http, $rateLimiter, $config);

    // We expect it to swallow the exception on page 2 and return page 1's results
    $query = new SearchQuery(new SearchTerm('test'), maxResults: 100);
    $results = $adapter->search($query);

    expect($results)->toHaveCount(1);
    expect($results[0]->title())->toBe('Page 1');
});

it('fetches citing works from Semantic Scholar citation traversal endpoint', function (): void {
    $http = new SemanticScholarSnowballHttp(
        new HttpResponse(200, [
            'data' => [
                [
                    'citingPaper' => [
                        'paperId' => 'S2-CITING',
                        'externalIds' => ['DOI' => '10.3000/citing'],
                        'title' => 'Citing Paper',
                        'year' => 2024,
                        'citationCount' => 12,
                        'authors' => [['name' => 'Ada Lovelace']],
                    ],
                ],
            ],
        ]),
    );
    $rateLimiter = new SemanticScholarSnowballRateLimiter;
    $adapter = new SemanticScholarAdapter(
        $http,
        $rateLimiter,
        new ProviderConfig(
            alias: 'semantic_scholar',
            baseUrl: 'https://api.semanticscholar.org',
            ratePerSecond: 10.0,
            timeoutSeconds: 9,
            apiKey: 's2-key',
        ),
    );

    $seed = semanticScholarSnowballTestWork(new WorkId(WorkIdNamespace::S2, 'S2-SEED'));
    $works = $adapter->fetchCitingWorks($seed, 5);

    expect($adapter->supportsSnowballing($seed, SnowballDirection::FORWARD))->toBeTrue()
        ->and($works)->toHaveCount(1)
        ->and($works[0]->title())->toBe('Citing Paper')
        ->and($works[0]->sourceProvider())->toBe('semantic_scholar')
        ->and($works[0]->ids()->findByNamespace(WorkIdNamespace::S2)?->value)->toBe('s2-citing')
        ->and($works[0]->ids()->findByNamespace(WorkIdNamespace::DOI)?->value)->toBe('10.3000/citing')
        ->and($http->calls)->toHaveCount(1)
        ->and($http->calls[0]['url'])->toBe('https://api.semanticscholar.org/graph/v1/paper/s2-seed/citations')
        ->and($http->calls[0]['query']['limit'])->toBe(5)
        ->and($http->calls[0]['headers']['x-api-key'])->toBe('s2-key')
        ->and($http->calls[0]['timeoutSeconds'])->toBe(9)
        ->and($rateLimiter->waits)->toBe(1);
});

it('fetches referenced works using DOI identifiers', function (): void {
    $http = new SemanticScholarSnowballHttp(
        new HttpResponse(200, [
            'data' => [
                [
                    'citedPaper' => [
                        'paperId' => 'S2-REFERENCE',
                        'externalIds' => ['DOI' => '10.3000/reference'],
                        'title' => 'Referenced Paper',
                    ],
                ],
            ],
        ]),
    );
    $adapter = new SemanticScholarAdapter(
        $http,
        new SemanticScholarSnowballRateLimiter,
        new ProviderConfig('semantic_scholar', 'https://api.semanticscholar.org', 10.0),
    );

    $seed = semanticScholarSnowballTestWork(new WorkId(WorkIdNamespace::DOI, '10.3000/seed'));
    $works = $adapter->fetchReferencedWorks($seed, 10);

    expect($adapter->supportsSnowballing($seed, SnowballDirection::BACKWARD))->toBeTrue()
        ->and($works)->toHaveCount(1)
        ->and($works[0]->title())->toBe('Referenced Paper')
        ->and($http->calls[0]['url'])->toBe('https://api.semanticscholar.org/graph/v1/paper/DOI:10.3000/seed/references')
        ->and($http->calls[0]['query']['limit'])->toBe(10);
});

it('paginates Semantic Scholar citation traversal with offsets', function (): void {
    $firstPage = array_map(
        static fn (int $index): array => [
            'citingPaper' => [
                'paperId' => "S2-CITING-{$index}",
                'title' => "Citing Paper {$index}",
            ],
        ],
        range(1, 100),
    );

    $http = new SemanticScholarSnowballHttp(
        new HttpResponse(200, ['data' => $firstPage]),
        new HttpResponse(200, [
            'data' => [
                [
                    'citingPaper' => [
                        'paperId' => 'S2-CITING-101',
                        'title' => 'Citing Paper 101',
                    ],
                ],
            ],
        ]),
    );
    $adapter = new SemanticScholarAdapter(
        $http,
        new SemanticScholarSnowballRateLimiter,
        new ProviderConfig('semantic_scholar', 'https://api.semanticscholar.org', 10.0),
    );

    $works = $adapter->fetchCitingWorks(
        semanticScholarSnowballTestWork(new WorkId(WorkIdNamespace::S2, 'S2-SEED')),
        101,
    );

    expect($works)->toHaveCount(101)
        ->and($http->calls)->toHaveCount(2)
        ->and($http->calls[0]['query']['limit'])->toBe(100)
        ->and($http->calls[0]['query'])->not->toHaveKey('offset')
        ->and($http->calls[1]['query']['limit'])->toBe(1)
        ->and($http->calls[1]['query']['offset'])->toBe(100);
});

it('returns no snowballing works for seeds without supported identifiers', function (): void {
    $http = new SemanticScholarSnowballHttp;
    $adapter = new SemanticScholarAdapter(
        $http,
        new SemanticScholarSnowballRateLimiter,
        new ProviderConfig('semantic_scholar', 'https://api.semanticscholar.org', 10.0),
    );

    $seed = ScholarlyWork::reconstitute(
        ids: WorkIdSet::empty(),
        title: 'No IDs',
        sourceProvider: 'test',
    );

    expect($adapter->supportsSnowballing($seed, SnowballDirection::FORWARD))->toBeFalse()
        ->and($adapter->fetchCitingWorks($seed, 10))->toBe([])
        ->and($adapter->fetchReferencedWorks($seed, 10))->toBe([])
        ->and($http->calls)->toBe([]);
});

function semanticScholarSnowballTestWork(WorkId ...$ids): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray($ids),
        title: 'Seed',
        sourceProvider: 'test',
    );
}

final class SemanticScholarSnowballHttp implements HttpClientPort
{
    /** @var list<array{url: string, query: array<string, mixed>, headers: array<string, string>, timeoutSeconds: ?int}> */
    public array $calls = [];

    /** @var list<HttpResponse> */
    private array $responses;

    public function __construct(HttpResponse ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public function get(
        string $url,
        array $query = [],
        array $headers = [],
        ?int $timeoutSeconds = null,
    ): HttpResponse {
        $this->calls[] = compact('url', 'query', 'headers', 'timeoutSeconds');

        return array_shift($this->responses) ?? new HttpResponse(200, ['data' => []]);
    }

    public function getAsync(
        string $url,
        array $query = [],
        array $headers = [],
        ?int $timeoutSeconds = null,
    ): PromiseInterface {
        return new FulfilledPromise($this->get($url, $query, $headers, $timeoutSeconds));
    }
}

final class SemanticScholarSnowballRateLimiter implements RateLimiterPort
{
    public int $waits = 0;

    public function waitForToken(): void
    {
        $this->waits++;
    }

    public function remainingTokens(): int
    {
        return 10;
    }

    public function capacity(): int
    {
        return 10;
    }

    public function tryConsume(): bool
    {
        return true;
    }

    public function ratePerSecond(): float
    {
        return 10.0;
    }
}
