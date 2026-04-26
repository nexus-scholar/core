# Class Specs — Search Ports

> **File:** `docs/spec-search-ports.md`
> **Namespace:** `Nexus\Search\Domain\Ports`

---

## `AcademicProviderPort` (interface)

**File:** `src/Search/Domain/Ports/AcademicProviderPort.php`

```php
interface AcademicProviderPort
{
    /**
     * Provider machine name. Must be stable, lowercase, snake_case.
     * Examples: 'openalex', 'semantic_scholar', 'arxiv', 'crossref',
     *           'pubmed', 'ieee', 'doaj'
     */
    public function alias(): string;

    /**
     * Search for works matching the query.
     * MUST call $this->rateLimiter->waitForToken() before each HTTP request.
     * MUST respect $query->maxResults and $query->offset.
     * MUST NOT store $rawData on returned works unless $query->includeRawData.
     * MUST return an empty array (not throw) when provider returns 0 results.
     *
     * @return ScholarlyWork[]
     * @throws ProviderUnavailable on HTTP 5xx or connection failure after retries
     */
    public function search(SearchQuery $query): array;

    /**
     * Fetch a single work by known external identifier.
     * Returns null if the provider cannot find or does not support this ID.
     *
     * @throws ProviderUnavailable on network failure
     */
    public function fetchById(WorkId $id): ?ScholarlyWork;

    /**
     * Whether this provider can resolve a given namespace.
     * Used to skip unnecessary fetchById calls.
     */
    public function supports(WorkIdNamespace $ns): bool;
}
```

---

## `RateLimiterPort` (interface)

**File:** `src/Search/Domain/Ports/RateLimiterPort.php`

```php
interface RateLimiterPort
{
    /**
     * Block the current process until a token is available, then consume it.
     * This MUST be called before every outbound HTTP request in every provider.
     * This is the single most important contract in the system.
     * The old package had a rate limiter that was never called — that must
     * never happen again. Any provider that bypasses this is broken by design.
     */
    public function waitForToken(): void;

    /**
     * Non-blocking: consume a token only if one is available right now.
     * Returns true if a token was consumed, false if the caller must wait.
     */
    public function tryConsume(): bool;

    /**
     * Return the configured rate (requests per second).
     */
    public function ratePerSecond(): float;
}
```

---

## `HttpClientPort` (interface)

**File:** `src/Search/Domain/Ports/HttpClientPort.php`

```php
interface HttpClientPort
{
    /**
     * Perform a GET request and return a parsed response.
     *
     * @param  array<string,mixed>  $query    URL query parameters
     * @param  array<string,string> $headers  HTTP headers
     * @throws ProviderUnavailable            On connection failure after retries
     */
    public function get(
        string $url,
        array  $query   = [],
        array  $headers = [],
    ): HttpResponse;
}

final class HttpResponse
{
    public function __construct(
        public readonly int    $statusCode,
        public readonly array  $body,       // decoded JSON (or empty array for non-JSON)
        public readonly string $rawBody     = '',
        public readonly array  $headers     = [],
    )

    public function ok(): bool           // 200–299
    public function notFound(): bool     // 404
    public function rateLimited(): bool  // 429
    public function serverError(): bool  // 500–599

    public function header(string $name): ?string
}
```

---

## `SearchCachePort` (interface)

**File:** `src/Search/Domain/Ports/SearchCachePort.php`

```php
interface SearchCachePort
{
    /**
     * Retrieve previously cached results for this key.
     * Returns null on cache miss.
     * @return ScholarlyWork[]|null
     */
    public function get(string $key): ?array;

    /**
     * Store results in the cache.
     * @param ScholarlyWork[] $results
     */
    public function put(string $key, array $results, int $ttlSeconds): void;

    /**
     * Invalidate all cache entries by bumping a global version counter.
     * MUST NOT rely on tag flushing (the old package's tag flush was a no-op).
     * Implementation: store a version integer, prefix all keys with it.
     */
    public function invalidateAll(): void;

    /**
     * Check existence without fetching the value.
     */
    public function has(string $key): bool;
}
```

---
