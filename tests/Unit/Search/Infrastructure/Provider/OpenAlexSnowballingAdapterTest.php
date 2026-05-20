<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Infrastructure\Provider\OpenAlexAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('fetches citing works from the OpenAlex cites filter', function (): void {
    $http = new OpenAlexSnowballHttp(new HttpResponse(200, [
        'results' => [
            openAlexSnowballRawWork(
                id: 'https://openalex.org/W456',
                doi: 'https://doi.org/10.1000/citing',
                title: 'Citing Work',
            ),
        ],
    ]));
    $rateLimiter = new OpenAlexSnowballRateLimiter();
    $adapter = new OpenAlexAdapter(
        $http,
        $rateLimiter,
        new ProviderConfig(
            alias: 'openalex',
            baseUrl: 'https://api.openalex.org',
            ratePerSecond: 10.0,
            timeoutSeconds: 7,
            mailTo: 'ops@example.com',
        ),
    );

    $seed = openAlexSnowballTestWork(new WorkId(WorkIdNamespace::OPENALEX, 'https://openalex.org/W123'));
    $works = $adapter->fetchCitingWorks($seed, 5);

    expect($adapter->supportsSnowballing($seed, SnowballDirection::FORWARD))->toBeTrue()
        ->and($works)->toHaveCount(1)
        ->and($works[0]->title())->toBe('Citing Work')
        ->and($works[0]->sourceProvider())->toBe('openalex')
        ->and($works[0]->ids()->findByNamespace(WorkIdNamespace::DOI)?->value)->toBe('10.1000/citing')
        ->and($http->calls)->toHaveCount(1)
        ->and($http->calls[0]['url'])->toBe('https://api.openalex.org/works')
        ->and($http->calls[0]['query']['filter'])->toBe('cites:W123')
        ->and($http->calls[0]['query']['per-page'])->toBe(5)
        ->and($http->calls[0]['query']['page'])->toBe(1)
        ->and($http->calls[0]['query']['mailto'])->toBe('ops@example.com')
        ->and($http->calls[0]['timeoutSeconds'])->toBe(7)
        ->and($rateLimiter->waits)->toBe(1);
});

it('fetches referenced works from OpenAlex seed metadata', function (): void {
    $http = new OpenAlexSnowballHttp(new HttpResponse(200, [
        'results' => [
            openAlexSnowballRawWork(id: 'https://openalex.org/W111', title: 'First Reference'),
            openAlexSnowballRawWork(id: 'https://openalex.org/W222', title: 'Second Reference'),
        ],
    ]));
    $adapter = new OpenAlexAdapter(
        $http,
        new OpenAlexSnowballRateLimiter(),
        new ProviderConfig('openalex', 'https://api.openalex.org', 10.0, mailTo: 'ops@example.com'),
    );
    $seed = ScholarlyWork::reconstitute(
        ids: WorkIdSet::empty(),
        title: 'Seed',
        sourceProvider: 'test',
        rawData: [
            'referenced_works' => [
                'https://openalex.org/W111',
                'https://openalex.org/W222',
            ],
        ],
    );

    $works = $adapter->fetchReferencedWorks($seed, 10);

    expect($adapter->supportsSnowballing($seed, SnowballDirection::BACKWARD))->toBeTrue()
        ->and($works)->toHaveCount(2)
        ->and($works[0]->title())->toBe('First Reference')
        ->and($works[1]->title())->toBe('Second Reference')
        ->and($http->calls)->toHaveCount(1)
        ->and($http->calls[0]['query']['filter'])->toBe('openalex_id:W111|W222')
        ->and($http->calls[0]['query']['per-page'])->toBe(2);
});

it('resolves DOI seeds before OpenAlex forward snowballing', function (): void {
    $http = new OpenAlexSnowballHttp(
        new HttpResponse(200, ['id' => 'https://openalex.org/W999']),
        new HttpResponse(200, [
            'results' => [
                openAlexSnowballRawWork(id: 'https://openalex.org/W1000', title: 'Resolved Citing Work'),
            ],
        ]),
    );
    $adapter = new OpenAlexAdapter(
        $http,
        new OpenAlexSnowballRateLimiter(),
        new ProviderConfig('openalex', 'https://api.openalex.org', 10.0, mailTo: 'ops@example.com'),
    );

    $works = $adapter->fetchCitingWorks(
        openAlexSnowballTestWork(new WorkId(WorkIdNamespace::DOI, '10.1000/seed')),
        1,
    );

    expect($works)->toHaveCount(1)
        ->and($works[0]->title())->toBe('Resolved Citing Work')
        ->and($http->calls)->toHaveCount(2)
        ->and($http->calls[0]['url'])->toBe('https://api.openalex.org/works/https://doi.org/10.1000/seed')
        ->and($http->calls[1]['query']['filter'])->toBe('cites:W999');
});

it('returns no OpenAlex snowballing works for seeds without supported identifiers', function (): void {
    $http = new OpenAlexSnowballHttp();
    $adapter = new OpenAlexAdapter(
        $http,
        new OpenAlexSnowballRateLimiter(),
        new ProviderConfig('openalex', 'https://api.openalex.org', 10.0),
    );
    $seed = ScholarlyWork::reconstitute(
        ids: WorkIdSet::empty(),
        title: 'No IDs',
        sourceProvider: 'test',
    );

    expect($adapter->supportsSnowballing($seed, SnowballDirection::FORWARD))->toBeFalse()
        ->and($adapter->supportsSnowballing($seed, SnowballDirection::BACKWARD))->toBeFalse()
        ->and($adapter->fetchCitingWorks($seed, 10))->toBe([])
        ->and($adapter->fetchReferencedWorks($seed, 10))->toBe([])
        ->and($http->calls)->toBe([]);
});

function openAlexSnowballTestWork(WorkId ...$ids): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray($ids),
        title: 'Seed',
        sourceProvider: 'test',
    );
}

/**
 * @return array<string, mixed>
 */
function openAlexSnowballRawWork(string $id, ?string $doi = null, string $title = 'Work'): array
{
    $ids = ['openalex' => $id];

    if ($doi !== null) {
        $ids['doi'] = $doi;
    }

    return [
        'id' => $id,
        'ids' => $ids,
        'display_name' => $title,
        'publication_year' => 2024,
        'cited_by_count' => 3,
    ];
}

final class OpenAlexSnowballHttp implements HttpClientPort
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

        return array_shift($this->responses) ?? new HttpResponse(200, ['results' => []]);
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

final class OpenAlexSnowballRateLimiter implements RateLimiterPort
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
