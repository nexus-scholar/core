<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;

final class FakeHttpClient implements HttpClientPort
{
    /** @var list<array{url: string, query: array<string, mixed>, headers: array<string, string>, timeout: ?int}> */
    public array $calls = [];

    /** @var list<HttpResponse> */
    private array $responses;

    public function __construct(HttpResponse ...$responses)
    {
        $this->responses = $responses;
    }

    public function get(
        string $url,
        array $query = [],
        array $headers = [],
        ?int $timeoutSeconds = null,
    ): HttpResponse {
        $this->calls[] = [
            'url' => $url,
            'query' => $query,
            'headers' => $headers,
            'timeout' => $timeoutSeconds,
        ];

        return array_shift($this->responses) ?? new HttpResponse(404, []);
    }

    public function getAsync(
        string $url,
        array $query = [],
        array $headers = [],
        ?int $timeoutSeconds = null,
    ): PromiseInterface {
        return Create::promiseFor($this->get($url, $query, $headers, $timeoutSeconds));
    }
}

