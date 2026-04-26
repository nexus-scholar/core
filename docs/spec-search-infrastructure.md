# Class Specs — Search Infrastructure

> **File:** `docs/spec-search-infrastructure.md`
> **Namespace:** `Nexus\Search\Infrastructure`
> **Rule:** These classes implement ports. They may import Guzzle, PSR interfaces.
> **Rule:** They must NEVER import domain classes from other contexts directly.

---

## `BaseProviderAdapter` (abstract class)

**File:** `src/Search/Infrastructure/Providers/BaseProviderAdapter.php`

```php
abstract class BaseProviderAdapter implements AcademicProviderPort
{
    public function __construct(
        protected readonly HttpClientPort    $http,
        protected readonly RateLimiterPort   $rateLimiter,
        protected readonly ProviderConfig    $config,
    )

    /**
     * MUST be called by every concrete adapter before ANY HTTP request.
     * Centralizes: rate limiting + retry logic + error normalization.
     *
     * @throws ProviderUnavailable after maxRetries exhausted
     */
    final protected function request(
        string $url,
        array  $query   = [],
        array  $headers = [],
    ): HttpResponse

    /**
     * Normalize a raw provider response array into a ScholarlyWork.
     * Concrete adapters implement this.
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

    // Shared utility
    protected function extractString(array $data, string ...$keys): ?string
    protected function extractInt(array $data, string ...$keys): ?int
    protected function extractArray(array $data, string ...$keys): array
    protected function extractNestedString(array $data, string $path): ?string
    // $path uses dot-notation: 'primary_location.source.display_name'
}
```

**Implementation notes:**
- `request()` internally calls `$this->rateLimiter->waitForToken()` then `$this->http->get()`
- Retry: max 3 attempts, exponential backoff: 1s, 2s, 4s
- Retry on: 429, 500, 502, 503, 504, connection timeout
- Do not retry on: 404, 401, 403 — throw immediately
- `ProviderConfig` holds: baseUrl, ratePerSecond, timeout, apiKey (nullable), mailTo (nullable)

---

## `ProviderConfig` (Value Object)

**File:** `src/Search/Infrastructure/Providers/ProviderConfig.php`

```php
final class ProviderConfig
{
    public function __construct(
        public readonly string  $alias,
        public readonly string  $baseUrl,
        public readonly float   $ratePerSecond,    // e.g. 10.0 for OpenAlex, 1.0 for IEEE
        public readonly int     $timeoutSeconds    = 30,
        public readonly ?string $apiKey            = null,
        public readonly ?string $mailTo            = null,
        // MUST come from env, never hardcoded in source
        public readonly int     $maxRetries        = 3,
        public readonly bool    $enabled           = true,
    )
}
```

---

## `OpenAlexAdapter`

**File:** `src/Search/Infrastructure/Providers/OpenAlexAdapter.php`

```php
final class OpenAlexAdapter extends BaseProviderAdapter
{
    public function alias(): string  // 'openalex'

    public function supports(WorkIdNamespace $ns): bool
    // Supports: DOI, OPENALEX, ARXIV, PUBMED

    public function search(SearchQuery $query): array
    // GET https://api.openalex.org/works
    // params: search={term}, filter=publication_year:{from}-{to},
    //         per-page={maxResults}, page={page}, mailto={config->mailTo}
    // Paginates automatically if maxResults > 200 (OpenAlex page limit)

    public function fetchById(WorkId $id): ?ScholarlyWork
    // GET https://api.openalex.org/works/{id}

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    // Maps OpenAlex work object to ScholarlyWork
    // Key field paths:
    //   ids.doi              → WorkId(DOI, ...)
    //   ids.openalex         → WorkId(OPENALEX, ...)
    //   ids.pmid             → WorkId(PUBMED, ...)
    //   display_name         → title
    //   publication_year     → year
    //   primary_location.source.display_name → venue.name
    //   primary_location.source.issn_l       → venue.issn
    //   authorships[].author.display_name    → authors
    //   authorships[].author.orcid           → author orcid
    //   cited_by_count                       → citedByCount
    //   is_retracted                         → isRetracted
    //   abstract_inverted_index              → abstract (reconstruct from inverted index)
}
```

**Note on abstract reconstruction:**
OpenAlex returns abstract as an inverted index `{"word": [positions]}`. The adapter must reconstruct the original string from this structure. This logic belongs in `OpenAlexAdapter::reconstructAbstract(array $invertedIndex): string`.

---

## `SemanticScholarAdapter`

**File:** `src/Search/Infrastructure/Providers/SemanticScholarAdapter.php`

```php
final class SemanticScholarAdapter extends BaseProviderAdapter
{
    public function alias(): string  // 'semantic_scholar'

    public function supports(WorkIdNamespace $ns): bool
    // Supports: DOI, S2, ARXIV

    public function search(SearchQuery $query): array
    // POST https://api.semanticscholar.org/graph/v1/paper/search
    // rate: 1 req/sec without key, 10 req/sec with key

    public function fetchById(WorkId $id): ?ScholarlyWork
    // GET https://api.semanticscholar.org/graph/v1/paper/{id}
    // id can be: DOI:10.x, ARXIV:..., or S2 paperId

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    // Key field paths:
    //   paperId             → WorkId(S2, ...)
    //   externalIds.DOI     → WorkId(DOI, ...)
    //   externalIds.ArXiv   → WorkId(ARXIV, ...)
    //   title               → title
    //   year                → year
    //   venue               → venue.name
    //   authors[].name      → author full name
    //   authors[].authorId  → author S2 ID (store as orcid if no ORCID available)
    //   citationCount       → citedByCount
    //   isOpenAccess        → (informational)
    //   abstract            → abstract
}
```

---

## `ArXivAdapter`

**File:** `src/Search/Infrastructure/Providers/ArXivAdapter.php`

```php
final class ArXivAdapter extends BaseProviderAdapter
{
    public function alias(): string  // 'arxiv'

    public function supports(WorkIdNamespace $ns): bool
    // Supports: ARXIV only (no DOI from arXiv API directly)

    public function search(SearchQuery $query): array
    // GET http://export.arxiv.org/api/query
    // params: search_query=all:{term}, start={offset}, max_results={maxResults}
    // Response: Atom XML — parse with SimpleXML
    // Rate: 3 req/sec

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    // $raw is parsed from Atom XML entry into array
    // Key paths:
    //   id          → extract arXiv ID from URL (e.g. 2301.12345)
    //   title       → title (strip whitespace/newlines)
    //   summary     → abstract
    //   published   → year (extract year from date string)
    //   author[].name → authors
    //   category[@term] → venue type hint

    private function parseAtomXml(string $xml): array
    // Parses Atom feed, returns array of entry arrays
}
```

---

## `CrossrefAdapter`

**File:** `src/Search/Infrastructure/Providers/CrossrefAdapter.php`

```php
final class CrossrefAdapter extends BaseProviderAdapter
{
    public function alias(): string  // 'crossref'

    public function supports(WorkIdNamespace $ns): bool
    // Supports: DOI, PUBMED (via link)

    public function search(SearchQuery $query): array
    // GET https://api.crossref.org/works
    // params: query={term}, filter=from-pub-date:{from},until-pub-date:{to},
    //         rows={maxResults}, offset={offset}, mailto={config->mailTo}

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    // Key paths:
    //   DOI                       → WorkId(DOI, ...)
    //   title[0]                  → title
    //   author[].family/given     → authors
    //   author[].ORCID            → orcid
    //   published.date-parts[0][0]→ year
    //   container-title[0]        → venue.name
    //   ISSN[0]                   → venue.issn
    //   is-referenced-by-count    → citedByCount
    //   type                      → venue.type mapping
}
```

---

## `GuzzleHttpClient`

**File:** `src/Search/Infrastructure/Http/GuzzleHttpClient.php`

```php
final class GuzzleHttpClient implements HttpClientPort
{
    public function __construct(
        private readonly \GuzzleHttp\Client $guzzle,
    )

    public static function create(int $timeoutSeconds = 30): self
    // Uses composer/ca-bundle for SSL verification:
    // verify: \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()
    // NEVER commit cacert.pem — use CaBundle at runtime

    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    // Catches GuzzleException → wraps in ProviderUnavailable
    // Decodes JSON response body
    // Stores raw body string on HttpResponse
}
```

---

## `TokenBucketRateLimiter`

**File:** `src/Search/Infrastructure/RateLimit/TokenBucketRateLimiter.php`

```php
final class TokenBucketRateLimiter implements RateLimiterPort
{
    private float $tokens;
    private float $lastRefillTime;

    public function __construct(
        private readonly float $ratePerSecond,
        private readonly float $capacity,
        // typically = ratePerSecond (burst = 1 second worth)
    )

    public function waitForToken(): void
    // Token bucket algorithm:
    // 1. Compute elapsed seconds since last refill
    // 2. Add elapsed * ratePerSecond tokens (up to capacity)
    // 3. If tokens < 1.0: sleep for (1.0 - tokens) / ratePerSecond microseconds
    // 4. Consume 1.0 token
    // Uses hrtime(true) for high-resolution timing, not microtime()

    public function tryConsume(): bool
    // Refill without sleeping. Return true if token available, false if not.

    public function ratePerSecond(): float
}
```

**Tests:**
```
it_allows_immediate_first_request
it_blocks_until_token_is_available
it_accumulates_tokens_over_time_up_to_capacity
it_returns_false_from_try_consume_when_empty
it_returns_true_from_try_consume_when_token_available
it_does_not_accumulate_beyond_capacity
```

---

## `LaravelSearchCache`

**File:** `src/Search/Infrastructure/Cache/LaravelSearchCache.php`

```php
final class LaravelSearchCache implements SearchCachePort
{
    public function __construct(
        private readonly \Illuminate\Contracts\Cache\Repository $cache,
        private readonly string $keyPrefix = 'nexus:search:',
    )

    public function get(string $key): ?array
    public function put(string $key, array $results, int $ttlSeconds): void
    public function invalidateAll(): void
    // Stores a version integer. All keys are prefixed with version.
    // invalidateAll() increments the version, effectively expiring all keys.
    public function has(string $key): bool

    private function versioned(string $key): string
    // Returns "{keyPrefix}v{version}:{key}"
    // Version retrieved from cache on first call, stored as property
}
```

---

## `NullSearchCache`

**File:** `src/Search/Infrastructure/Cache/NullSearchCache.php`

```php
final class NullSearchCache implements SearchCachePort
{
    public function get(string $key): ?array       { return null; }
    public function put(string $key, array $results, int $ttlSeconds): void {}
    public function invalidateAll(): void          {}
    public function has(string $key): bool         { return false; }
}
```

Use in: tests, standalone usage, dev mode.

---
