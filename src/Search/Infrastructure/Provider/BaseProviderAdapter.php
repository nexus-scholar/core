<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use GuzzleHttp\Promise\PromiseInterface;
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
        protected readonly ?\Psr\Log\LoggerInterface $logger = null,
        private readonly ?\Closure $sleeper = null,
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

                $this->logger?->warning("Provider {$this->alias()} unavailable: {$e->getMessage()}", [
                    'url' => $url,
                    'attempt' => $attempt,
                ]);

                if ($attempt >= $this->config->maxRetries) {
                    $this->logger?->error("Provider {$this->alias()} failed permanently after {$attempt} attempts.", [
                        'url' => $url,
                        'exception' => $e,
                    ]);
                    throw $e;
                }

                $jitter = (random_int(0, 1000) / 1000.0); // 0 to 1 second jitter
                ($this->sleeper ?? static fn(float $s) => usleep((int)($s * 1_000_000)))($backoff + $jitter);
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

                $this->logger?->warning("Provider {$this->alias()} rate limited or server error", [
                    'status' => $response->statusCode,
                    'url' => $url,
                    'attempt' => $attempt,
                ]);

                if ($attempt >= $this->config->maxRetries) {
                    $this->logger?->error("Provider {$this->alias()} failed permanently after {$attempt} attempts with status {$response->statusCode}.");
                    throw new ProviderUnavailable(
                        $this->alias(),
                        "HTTP {$response->statusCode} after {$attempt} attempt(s)."
                    );
                }

                $jitter = (random_int(0, 1000) / 1000.0); // 0 to 1 second jitter
                ($this->sleeper ?? static fn(float $s) => usleep((int)($s * 1_000_000)))($backoff + $jitter);
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

    /**
     * Default implementation of searchAsync.
     * Most adapters follow a simple request -> extract -> normalize pattern.
     * Complex adapters (like PubMed) MUST override this.
     */
    public function searchAsync(SearchQuery $query): PromiseInterface
    {
        $params = $this->paginationParams($query);

        return $this->requestAsync($this->config->baseUrl, $params)
            ->then(function (HttpResponse $response) use ($query) {
                if (! $response->ok()) {
                    return [];
                }

                $items = $this->extractItems($response->body);

                return array_map(fn (array $raw) => $this->normalize($raw, $query), $items);
            });
    }

    /**
     * Async version of request.
     *
     * TODO: Implement async-safe rate limiting and retries.
     * Currently blocks for the rate limit token then returns an async HTTP promise.
     */
    final protected function requestAsync(
        string $url,
        array  $query   = [],
        array  $headers = [],
    ): PromiseInterface {
        $this->rateLimiter->waitForToken();

        return $this->http->getAsync($url, $query, $headers);
    }

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

    /**
     * Parse an author display name into family/given parts.
     * Handles "Family, Given" (comma-delimited) and "Given Family" (space-delimited) formats.
     *
     * @return array{family: string, given: ?string}
     */
    protected function parseAuthorName(string $name): array
    {
        $name = trim($name);

        // "Family, Given" format
        if (str_contains($name, ',')) {
            $parts = explode(',', $name, 2);

            return [
                'family' => trim($parts[0]),
                'given'  => isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null,
            ];
        }

        // "Given Family" format — last token is family name
        $parts = preg_split('/\s+/', $name);

        if (count($parts) === 1) {
            return ['family' => $parts[0], 'given' => null];
        }

        return [
            'family' => array_pop($parts),
            'given'  => implode(' ', $parts),
        ];
    }
}
