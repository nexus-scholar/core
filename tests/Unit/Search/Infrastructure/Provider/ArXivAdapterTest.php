<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Infrastructure\Provider\ArXivAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;

it('adds submitted date range to arxiv search and drops out of range works', function (): void {
    $http = new class implements HttpClientPort {
        /** @var array<string, mixed> */
        public array $lastQuery = [];

        public function get(string $url, array $query = [], array $headers = []): HttpResponse
        {
            $this->lastQuery = $query;

            return new HttpResponse(
                statusCode: 200,
                body: [],
                rawBody: <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <entry>
    <id>http://arxiv.org/abs/2201.00001v1</id>
    <title>Old texture result</title>
    <summary>Out of range.</summary>
    <published>2022-01-01T00:00:00Z</published>
    <author><name>Old Author</name></author>
  </entry>
  <entry>
    <id>http://arxiv.org/abs/2301.00001v1</id>
    <title>Current texture result</title>
    <summary>In range.</summary>
    <published>2023-01-01T00:00:00Z</published>
    <author><name>Current Author</name></author>
  </entry>
</feed>
XML,
            );
        }

        public function getAsync(string $url, array $query = [], array $headers = []): PromiseInterface
        {
            return new FulfilledPromise($this->get($url, $query, $headers));
        }
    };

    $adapter = new ArXivAdapter(
        http: $http,
        rateLimiter: new class implements RateLimiterPort {
            public function waitForToken(): void {}

            public function remainingTokens(): int
            {
                return 1;
            }

            public function capacity(): int
            {
                return 1;
            }

            public function tryConsume(): bool
            {
                return true;
            }

            public function ratePerSecond(): float
            {
                return 1.0;
            }
        },
        config: new ProviderConfig(
            alias: 'arxiv',
            baseUrl: 'https://export.arxiv.org/api',
            ratePerSecond: 1.0,
        ),
    );

    $works = $adapter->search(new SearchQuery(
        term: new SearchTerm('texture'),
        yearRange: new YearRange(2023),
        maxResults: 2,
    ));

    expect($http->lastQuery['search_query'])
        ->toBe('(all:texture) AND submittedDate:[202301010000 TO 999912312359]');

    expect($works)->toHaveCount(1);
    expect($works[0]->title())->toBe('Current texture result');
    expect($works[0]->year())->toBe(2023);
});
