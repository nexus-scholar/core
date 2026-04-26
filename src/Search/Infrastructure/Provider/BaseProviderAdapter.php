<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;

/**
 * Abstract base for all provider adapters.
 *
 * The request() method is FINAL and centralises:
 *   - rate limiting (waitForToken before every request)
 *   - retry with exponential back-off (1s / 2s / 4s)
 *   - error normalisation into ProviderUnavailable
 *
 * Concrete adapters implement normalize(), paginationParams(), extractItems().
 */
abstract class BaseProviderAdapter implements AcademicProviderPort
{
    public function __construct(
        protected readonly HttpClientPort  $http,
        protected readonly RateLimiterPort $rateLimiter,
        protected readonly ProviderConfig  $config,
    ) {}

    /**
     * MUST be called by every concrete adapter before ANY HTTP request.
     *
     * @throws ProviderUnavailable after maxRetries exhausted
     */
    final protected function request(
        string $url,
        array  $query   = [],
        array  $headers = [],
    ): HttpResponse {
        $attempt = 0;
        $backoff  = 1; // seconds

        while (true) {
            $this->rateLimiter->waitForToken();

            try {
                $response = $this->http->get($url, $query, $headers);
            } catch (ProviderUnavailable $e) {
                $attempt++;

                if ($attempt >= $this->config->maxRetries) {
                    throw $e;
                }

                sleep($backoff);
                $backoff *= 2;
                continue;
            }

            // Do not retry on client errors — fail immediately
            if ($response->statusCode === 401
                || $response->statusCode === 403
                || $response->statusCode === 404) {
                return $response;
            }

            // Retry on rate-limit or server errors
            if ($response->rateLimited() || $response->serverError()) {
                $attempt++;

                if ($attempt >= $this->config->maxRetries) {
                    throw new ProviderUnavailable(
                        $this->alias(),
                        "HTTP {$response->statusCode} after {$attempt} attempt(s)."
                    );
                }

                sleep($backoff);
                $backoff *= 2;
                continue;
            }

            return $response;
        }
    }

    /**
     * Normalize a raw provider response array into a ScholarlyWork.
     */
    abstract protected function normalize(array $raw, SearchQuery $query): ScholarlyWork;

    /**
     * Build pagination parameters for a query.
     * Each adapter handles different offset/page/cursor schemes.
     */
    abstract protected function paginationParams(SearchQuery $query): array;

    /**
     * Extract result items from a raw response body.
     * OpenAlex uses 'results', arXiv uses entries from XML, etc.
     */
    abstract protected function extractItems(array $body): array;

    // ── Shared utilities ─────────────────────────────────────────────────────

    protected function extractString(array $data, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    protected function extractInt(array $data, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (int) $data[$key];
            }
        }

        return null;
    }

    protected function extractArray(array $data, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return [];
    }

    /**
     * Extract a nested value using dot-notation path.
     * e.g. 'primary_location.source.display_name'
     */
    protected function extractNestedString(array $data, string $path): ?string
    {
        $segments = explode('.', $path);
        $current  = $data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return is_string($current) && $current !== '' ? $current : null;
    }
}
