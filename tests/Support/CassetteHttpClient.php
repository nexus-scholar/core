<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Symfony\Component\Yaml\Yaml;

final class CassetteHttpClient implements HttpClientPort
{
    /** @var list<array<string, mixed>> */
    private array $records;

    /** @var array<int, true> */
    private array $consumed = [];

    /** @var list<array{url: string, query: array<string, mixed>, headers: array<string, string>}> */
    public array $calls = [];

    public function __construct(string $cassette)
    {
        $path = __DIR__.'/../Fixture/vcr_cassettes/'.$cassette;
        $records = Yaml::parseFile($path);

        if (! is_array($records)) {
            throw new \InvalidArgumentException("Cassette {$cassette} did not contain response records.");
        }

        $this->records = array_values($records);
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
        ];

        $fullUrl = $this->fullUrl($url, $query);
        $record = $this->nextRecord($fullUrl);

        if ($record === null) {
            throw new ProviderUnavailable('cassette', 'Cassette exhausted before request '.$url);
        }

        $response = $record['response'] ?? [];
        $rawBody = (string) ($response['body'] ?? '');
        $headers = is_array($response['headers'] ?? null) ? $response['headers'] : [];
        $body = $this->decodedBody($rawBody, $headers);

        return new HttpResponse(
            statusCode: (int) ($response['status']['code'] ?? 200),
            body: $body,
            rawBody: $rawBody,
            headers: $headers,
        );
    }

    public function getAsync(
        string $url,
        array $query = [],
        array $headers = [],
        ?int $timeoutSeconds = null,
    ): PromiseInterface {
        return new FulfilledPromise($this->get($url, $query, $headers, $timeoutSeconds));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextRecord(string $fullUrl): ?array
    {
        foreach ($this->records as $index => $record) {
            if (isset($this->consumed[$index])) {
                continue;
            }

            if (($record['request']['url'] ?? null) === $fullUrl) {
                $this->consumed[$index] = true;

                return $record;
            }
        }

        foreach ($this->records as $index => $record) {
            if (! isset($this->consumed[$index])) {
                $this->consumed[$index] = true;

                return $record;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function fullUrl(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function decodedBody(string $rawBody, array $headers): array
    {
        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }
}
