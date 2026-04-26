<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

final class HttpResponse
{
    public function __construct(
        public readonly int    $statusCode,
        public readonly array  $body,
        public readonly string $rawBody  = '',
        public readonly array  $headers  = [],
    ) {}

    public function ok(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function notFound(): bool
    {
        return $this->statusCode === 404;
    }

    public function rateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    public function serverError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    public function header(string $name): ?string
    {
        $normalized = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }

        return null;
    }
}

interface HttpClientPort
{
    /**
     * Perform a GET request and return a parsed response.
     *
     * @param  array<string,mixed>  $query    URL query parameters
     * @param  array<string,string> $headers  HTTP headers
     * @throws \Nexus\Search\Domain\Exception\ProviderUnavailable On connection failure after retries
     */
    public function get(
        string $url,
        array  $query   = [],
        array  $headers = [],
    ): HttpResponse;

    /**
     * Perform an asynchronous GET request.
     * Returns a promise that resolves to an HttpResponse.
     */
    public function getAsync(
        string $url,
        array  $query   = [],
        array  $headers = [],
    ): \GuzzleHttp\Promise\PromiseInterface;
}
