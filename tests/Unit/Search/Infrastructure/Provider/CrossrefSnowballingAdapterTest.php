<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Infrastructure\Provider\CrossrefAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('fetches referenced works from Crossref DOI metadata', function (): void {
    $http = new CrossrefSnowballHttp(new HttpResponse(200, [
        'message' => [
            'reference' => [
                [
                    'DOI' => '10.5555/reference',
                    'article-title' => 'Referenced Work',
                    'year' => '2020',
                    'journal-title' => 'Journal of References',
                    'author' => 'Lovelace, Ada',
                ],
                [
                    'unstructured' => 'Reference without DOI. 2019.',
                    'year' => '2019',
                ],
            ],
        ],
    ]));
    $rateLimiter = new CrossrefSnowballRateLimiter;
    $adapter = new CrossrefAdapter(
        $http,
        $rateLimiter,
        new ProviderConfig(
            alias: 'crossref',
            baseUrl: 'https://api.crossref.org',
            ratePerSecond: 15.0,
            timeoutSeconds: 8,
            mailTo: 'ops@example.com',
        ),
    );

    $seed = crossrefSnowballTestWork(new WorkId(WorkIdNamespace::DOI, '10.1000/seed'));
    $works = $adapter->fetchReferencedWorks($seed, 5);

    expect($adapter->supportsSnowballing($seed, SnowballDirection::BACKWARD))->toBeTrue()
        ->and($works)->toHaveCount(2)
        ->and($works[0]->title())->toBe('Referenced Work')
        ->and($works[0]->sourceProvider())->toBe('crossref')
        ->and($works[0]->ids()->findByNamespace(WorkIdNamespace::DOI)?->value)->toBe('10.5555/reference')
        ->and($works[0]->year())->toBe(2020)
        ->and($works[0]->venue()?->name)->toBe('Journal of References')
        ->and($works[0]->authors()->first()?->fullName())->toBe('Ada Lovelace')
        ->and($works[1]->title())->toBe('Reference without DOI. 2019.')
        ->and($http->calls)->toHaveCount(1)
        ->and($http->calls[0]['url'])->toBe('https://api.crossref.org/works/10.1000/seed')
        ->and($http->calls[0]['query']['mailto'])->toBe('ops@example.com')
        ->and($http->calls[0]['timeoutSeconds'])->toBe(8)
        ->and($rateLimiter->waits)->toBe(1);
});

it('does not model Crossref public metadata as forward snowballing', function (): void {
    $http = new CrossrefSnowballHttp;
    $adapter = new CrossrefAdapter(
        $http,
        new CrossrefSnowballRateLimiter,
        new ProviderConfig('crossref', 'https://api.crossref.org', 15.0),
    );
    $seed = crossrefSnowballTestWork(new WorkId(WorkIdNamespace::DOI, '10.1000/seed'));

    expect($adapter->supportsSnowballing($seed, SnowballDirection::FORWARD))->toBeFalse()
        ->and($adapter->fetchCitingWorks($seed, 10))->toBe([])
        ->and($http->calls)->toBe([]);
});

it('returns no Crossref references for seeds without DOI identifiers', function (): void {
    $http = new CrossrefSnowballHttp;
    $adapter = new CrossrefAdapter(
        $http,
        new CrossrefSnowballRateLimiter,
        new ProviderConfig('crossref', 'https://api.crossref.org', 15.0),
    );
    $seed = ScholarlyWork::reconstitute(
        ids: WorkIdSet::empty(),
        title: 'No DOI',
        sourceProvider: 'test',
    );

    expect($adapter->supportsSnowballing($seed, SnowballDirection::BACKWARD))->toBeFalse()
        ->and($adapter->fetchReferencedWorks($seed, 10))->toBe([])
        ->and($http->calls)->toBe([]);
});

function crossrefSnowballTestWork(WorkId ...$ids): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray($ids),
        title: 'Seed',
        sourceProvider: 'test',
    );
}

final class CrossrefSnowballHttp implements HttpClientPort
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

        return array_shift($this->responses) ?? new HttpResponse(200, ['message' => ['reference' => []]]);
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

final class CrossrefSnowballRateLimiter implements RateLimiterPort
{
    public int $waits = 0;

    public function waitForToken(): void
    {
        $this->waits++;
    }

    public function remainingTokens(): int
    {
        return 15;
    }

    public function capacity(): int
    {
        return 15;
    }

    public function tryConsume(): bool
    {
        return true;
    }

    public function ratePerSecond(): float
    {
        return 15.0;
    }
}
