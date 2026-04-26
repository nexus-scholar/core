# nexus-php — Deep Code Review

**Repository:** [nexus-scholar/nexus-php](https://github.com/nexus-scholar/nexus-php)
**Reviewed at commit:** `f9994d1`
**PHP target:** 8.3+
**Test suite:** 349 tests (Pest v3)

---

## Executive Summary

`nexus-php` is a well-structured, PHP 8.3 library for Systematic Literature Reviews (SLR). The architecture is clean and the domain model is solid. However, the review uncovered **five significant bugs or design gaps** that affect correctness, along with a set of medium and minor issues. The most critical findings are: (1) the `RateLimiter` is never wired into any provider, meaning all API rate limits are silently unenforced; (2) the `AggressiveStrategy` is advertised in the README but does not exist in the codebase; (3) `NexusSearcher::search()` mutates a shared singleton in a way that makes concurrent requests unsafe; (4) the cache key omits query dimensions that affect results; and (5) `cacert.pem` is committed to the repository and will become stale.

---

## Architecture Overview

The package follows a clear layered design:

```
Query → NexusService → [Provider] → Generator<Document>
                                      ↓
                             ConservativeStrategy (dedup)
                                      ↓
                         CitationGraphBuilder → Graph
                                      ↓
                        NetworkAnalyzer / GraphExporter
```

- **Models** are PHP 8 readonly-friendly constructor-promoted value objects — good.
- **Providers** extend `BaseProvider` and implement `search(Query): Generator` — clean abstraction.
- **Deduplication** uses a Union-Find structure — algorithmically correct and efficient.
- **Laravel integration** is a thin adapter layer that does not pollute core classes — excellent separation.

---

## Critical Issues

### 1. Rate Limiter Is Never Invoked (Silent No-Op)

`src/Utils/RateLimiter.php` is a well-implemented token-bucket limiter with `waitForToken()`. However, not a single provider (`OpenAlexProvider`, `ArxivProvider`, `CrossrefProvider`, `SemanticScholarProvider`, `PubMedProvider`, `IEEEProvider`, `DOAJProvider`) instantiates or calls it. `BaseProvider` also does not call it. The `ProviderConfig` carries a `$rateLimit: float` field, but that value is never read during HTTP calls.

**Impact:** Every provider fires requests as fast as PHP can loop. arXiv (3/sec), Semantic Scholar (1/sec), and IEEE Xplore (1/sec) will ban the caller quickly. The README's rate-limit table is misleading — those limits are documented but not enforced.

**Fix:** Wire the limiter into `BaseProvider::makeRawRequest()`:

```php
// In BaseProvider constructor
private RateLimiter $rateLimiter;

public function __construct(ProtectedProviderConfig $config, ?Client $client = null)
{
    parent::__construct($config, $client);
    $this->rateLimiter = new RateLimiter(
        rate: $this->config->rateLimit,
        capacity: (int) ceil($this->config->rateLimit)
    );
}

protected function makeRawRequest(string $url, array $params = [], array $headers = []): ResponseInterface
{
    $this->rateLimiter->waitForToken();
    // ... existing code
}
```

---

### 2. `AggressiveStrategy` Advertised But Missing

The README states: *"Built-in conservative and aggressive deduplication strategies."* The `DeduplicationStrategyName` enum presumably includes `AGGRESSIVE`, and `config.yml` supports `strategy: "conservative"`, implying a second value. But `src/Dedup/` contains only `ConservativeStrategy.php` and `DeduplicationStrategy.php`. There is no `AggressiveStrategy`.

**Impact:** Any code that passes `DeduplicationStrategyName::AGGRESSIVE` to a factory will either throw or silently fall back. Documentation mismatch erodes user trust.

**Fix:** Either implement `AggressiveStrategy` (suggested: lower the fuzzy threshold, add author name matching, cross-field DOI prefix matching) or remove all references to it from the README, `config.yml`, and any enum cases.

---

### 3. `NexusSearcher::search()` Mutates a Shared Singleton

```php
// NexusSearcher.php line 30
$this->service->clearProviders();

$providerNames = $providers ?? $this->config->getEnabledProviders();
foreach ($providerNames as $name) {
    $provider = ProviderFactory::makeFromConfig($name, $this->config);
    $this->service->registerProvider($provider);
}
```

`NexusService` is bound as a singleton in `NexusServiceProvider::register()`. `NexusSearcher` calls `clearProviders()` then re-registers providers on every call. In a web context (FPM, Octane, Swoole), two simultaneous requests will race: Request A clears providers, Request B clears providers, Request A re-registers — Request B now searches against Request A's providers or an empty set.

**Impact:** Flaky results or empty responses under any concurrency. Completely broken under Swoole/Octane.

**Fix (option A — preferred):** Remove the `clearProviders()` / `registerProvider()` cycle from `NexusSearcher`. Pre-register providers once during service boot in `NexusServiceProvider::boot()`:

```php
// In NexusServiceProvider::register()
$this->app->singleton(NexusService::class, function ($app) {
    $service = new NexusService();
    $config = $app->make(NexusConfig::class);
    foreach (ProviderFactory::makeAllFromConfig($config) as $provider) {
        $service->registerProvider($provider);
    }
    return $service;
});
```

**Fix (option B):** Make `NexusSearcher::search()` construct a local, ephemeral `NexusService` instance instead of mutating the singleton.

---

### 4. Cache Key Omits Query Dimensions

```php
private function getCacheKey(Query $query, ?array $providers): string
{
    $key = 'nexus:search:'.md5($query->text.':'.($query->yearMin ?? '').':'.($query->yearMax ?? ''));
    // ...
}
```

`Query` also carries `$language`, `$maxResults`, `$offset`, and `$metadata` (which can contain document `types`). Two queries that differ only in `language` or `maxResults` will get the same cache key and return stale results for the second call.

**Fix:**

```php
private function getCacheKey(Query $query, ?array $providers): string
{
    $parts = [
        $query->text,
        $query->yearMin ?? '',
        $query->yearMax ?? '',
        $query->language,
        $query->maxResults ?? '',
        $query->offset,
        json_encode($query->metadata),
    ];
    $key = 'nexus:search:' . md5(implode(':', $parts));

    if ($providers !== null) {
        sort($providers); // normalize order
        $key .= ':' . implode(',', $providers);
    }

    return $key;
}
```

Note also: `clearAllCache()` calls `$this->cache->tags(['nexus-search'])` but `put()` never tags keys — so `clearAllCache()` is a no-op on all cache drivers. Either add tag-based storage or use a key prefix scan.

---

### 5. `cacert.pem` Committed to the Repository

`cacert.pem` (226 KB) is committed at the root. `BaseProvider::findCACertPath()` resolves it at runtime. This creates two problems:

1. **Staleness:** CA certificate bundles expire. The committed bundle will become invalid as certificate authorities rotate, causing silent TLS verification failures months later.
2. **Bloat:** 226 KB is added to every `composer install`.

**Fix:** Use [`composer/ca-bundle`](https://github.com/composer/ca-bundle) — the Composer project's maintained, always-current CA bundle resolver:

```php
use Composer\CaBundle\CaBundle;

private function createDefaultClient(): Client
{
    return new Client([
        'timeout' => $this->config->timeout,
        'verify'  => CaBundle::getSystemCaRootBundlePath(),
    ]);
}
```

Add to `composer.json`: `"composer/ca-bundle": "^1.3"`. Then delete `cacert.pem` and the `findCACertPath()` method.

---

## Medium Issues

### 6. `SnowballService` Hardcodes a `ConservativeStrategy` in Its Constructor

```php
public function __construct(private SnowballConfig $config, SnowballProviderInterface ...$providers)
{
    $dedupConfig = new DeduplicationConfig(
        strategy: DeduplicationStrategyName::CONSERVATIVE,
        fuzzyThreshold: 97,
        maxYearGap: 1
    );
    $this->dedupStrategy = new ConservativeStrategy($dedupConfig);
}
```

The constructor creates a concrete `ConservativeStrategy` with hardcoded thresholds — ignoring any `DeduplicationConfig` the caller might want to pass. The `setDeduplicationStrategy()` setter exists as an escape hatch, but this is poor default behaviour: the thresholds (97, 1) cannot be varied without calling the setter after construction.

**Fix:** Accept a `?DeduplicationStrategy $dedupStrategy = null` constructor parameter. If `null`, build the default. This also makes the class trivially testable without the setter.

---

### 7. `fuseDocuments()` Has a Static, Non-Configurable Provider Priority

```php
$providerPriority = [
    'crossref' => 5, 'pubmed' => 4, 'openalex' => 3,
    'semantic_scholar' => 2, 's2' => 2, 'arxiv' => 1,
];
```

This priority list is hardcoded inside a static method in `DeduplicationStrategy`, and it diverges from the `DeduplicationConfig::$providerPriority` field that already exists on the config object. The config field is populated but never read by `fuseDocuments()`, which reads its own local array instead. This is a latent bug — if a user tunes `providerPriority` in their config, their setting is silently ignored.

**Fix:** Pass `$this->config->providerPriority` into `fuseDocuments()` or make it an instance method that reads `$this->config`.

---

### 8. `CitationGraphBuilder` — Co-Citation and Bibliographic Coupling Are O(n²)

```php
for ($i = 0; $i < $n; $i++) {
    for ($j = $i + 1; $j < $n; $j++) {
        $cocitationCount = count(array_intersect(
            $citingPapers[$paperA] ?? [],
            $citingPapers[$paperB] ?? []
        ));
```

Both `buildCoCitationGraph()` and `buildBibliographicCouplingGraph()` run O(n²) pair comparisons. For a corpus of 1,000 documents, that is ~500,000 `array_intersect` calls. For 5,000 documents (a medium SLR), it is ~12.5 million.

**Fix:** Use an inverted index. For co-citation, build a map of `citing_paper → [cited_papers]`, then for each cited paper, count how many citing papers it shares with every other cited paper. For bibliographic coupling, build `paper → [references]` and do the same inversion. This reduces time complexity from O(n²·k) to O(n·k), where k is the average neighbour count.

---

### 9. `NexusSearcher::searchAsync()` Returns a Non-Existent Property

```php
public function searchAsync(Query $query, ?array $providers = null, ?string $queue = null): string
{
    return SearchJob::dispatch($query, $providers ?? $this->config->getEnabledProviders())
        ->onQueue($queue ?? 'nexus')
        ->id;  // ← PendingDispatch has no ->id property
}
```

`Illuminate\Foundation\Bus\PendingDispatch` does not have a public `$id` property. This will throw a `TypeError` or return `null` (cast to empty string) at runtime, silently breaking all async search tracking.

**Fix:** If a job ID is needed, use the `Bus` facade to dispatch and capture the batch/chain ID, or generate a UUID before dispatch and pass it into the job:

```php
public function searchAsync(Query $query, ?array $providers = null, ?string $queue = null): string
{
    $jobId = (string) \Illuminate\Support\Str::uuid();
    SearchJob::dispatch($query, $providers ?? $this->config->getEnabledProviders(), $jobId)
        ->onQueue($queue ?? 'nexus');
    return $jobId;
}
```

---

### 10. `Document::$rawData` Stored on Every Document, Never Pruned

```php
public ?array $rawData = null
```

`OpenAlexProvider` (and likely all providers) stores the complete raw API response in `$rawData` on every `Document`. For OpenAlex, a single work response can be 2–5 KB. With `maxResults` unbounded (`PHP_INT_MAX`), a large search across 7 providers can easily load hundreds of megabytes of raw API payloads into a single PHP array.

**Fix:** Make `rawData` opt-in. Add a `bool $includeRawData = false` flag to `Query` and only populate `Document::$rawData` when explicitly requested. This aligns with the `output.include_raw` setting already in `config.yml` — it just needs to be threaded through.

---

## Minor Issues

### 11. `config.yml` Contains a Personal Email Address

```yaml
mailto: "bekhouche.mouadh@univ-oeb.dz"
```

A personal/institutional email address is committed to the repository root. This is used as the `mailto` polite-pool identifier for OpenAlex and Crossref. It should not be in source control.

**Fix:** Move to `.env` / environment variable. Document as `NEXUS_MAILTO=your@email.com` in a `.env.example`, and load it in `NexusConfig`. Add `config.yml` to `.gitignore` or replace with a `config.example.yml`.

---

### 12. `levenshtein()` in `ConservativeStrategy` Operates on Byte Length, Not Multibyte

```php
private function calculateFuzzyRatio(string $s1, string $s2): int
{
    $dist = levenshtein($s1, $s2);
    $maxLen = max(strlen($s1), strlen($s2)); // ← strlen, not mb_strlen
```

`levenshtein()` and `strlen()` are byte-count functions. For UTF-8 titles (Arabic, Chinese, accented Latin), a single character can be 2–4 bytes. The fuzzy ratio will be miscalculated and the `$threshold` comparison will be unreliable.

**Fix:** Use a Unicode-aware edit distance. A drop-in approach is to normalize both strings to ASCII using `iconv()` before levenshtein (since `normalizeTitle()` already strips diacritics via `preg_replace`). Alternatively, use `mb_str_split()` + a manual DP implementation.

---

### 13. `Query::$id` Uses a Weak Collision-Prone Generator

```php
$this->id = $id ?? 'Q'.substr(uniqid(), -5);
```

`uniqid()` returns a microsecond-precision timestamp hex string. Taking only the last 5 characters gives ~1 million values — collisions are realistic in a rapid-fire multi-query workflow. This ID is used as `queryId` on every returned `Document`, so collisions corrupt attribution.

**Fix:** `'Q' . bin2hex(random_bytes(5))` (10 hex chars, 2⁴⁰ space) or `Str::ulid()` in Laravel context.

---

### 14. `composer.json` Package Name Mismatch

```json
"name": "nexus/nexus-php"
```

But the README installation instruction says:
```bash
composer require mbsoft31/nexus-php
```

These do not match. Either the `composer.json` name is wrong, or the README install command is wrong. This will cause `composer require` to fail for any user following the README.

---

### 15. `ExternalIds` Normalizes DOI in Constructor but `DeduplicationStrategy::normalizeDoi()` Also Exists

`ExternalIds::__construct()` normalizes the DOI on assignment. `DeduplicationStrategy::normalizeDoi()` independently implements the same normalization. `SnowballService::buildIdSet()` then calls `strtolower($doc->externalIds->doi)` — a third normalization on an already-normalized value.

This is not a bug today, but it is a maintenance hazard: if the normalization rule changes (e.g., to handle new DOI prefixes), it must be updated in two or three places.

**Fix:** Make `ExternalIds::normalizeDoi()` the single source of truth (it already runs on construction), and call it as a static utility from `DeduplicationStrategy`. Remove the duplicate in `DeduplicationStrategy` and the redundant `strtolower()` calls in `SnowballService`.

---

### 16. `clearAllCache()` Tag Flush Is a No-Op

```php
public function clearAllCache(): bool
{
    $tags = $this->cache->tags(['nexus-search']);
    $tags?->flush();
    return true;
}
```

`NexusSearcher::search()` calls `$this->cache->put($cacheKey, $results, $ttl)` — without tags. So `tags(['nexus-search'])->flush()` flushes an empty tag namespace. `clearAllCache()` always silently does nothing.

**Fix:** Either use `$this->cache->tags(['nexus-search'])->put(...)` everywhere (requires a taggable driver like Redis), or switch to a key-prefix pattern with a version counter:

```php
private string $cacheVersion = 'v1';

public function clearAllCache(): bool
{
    $this->cacheVersion = (string) time(); // invalidates all keys
    Cache::put('nexus:cache_version', $this->cacheVersion, 86400 * 30);
    return true;
}
```

---

## Positive Highlights

The following patterns are done well and worth preserving:

- **Union-Find deduplication** — using `UnionFind` for clustering is algorithmically sound and efficient for the typical SLR corpus size (hundreds to low thousands of documents).
- **Generator-based streaming** — `NexusService::search()` yields from providers rather than collecting all results in memory before returning. This is the correct approach for pagination-heavy academic APIs.
- **`FieldExtractor` abstraction** — wrapping raw array access in a dedicated extractor class keeps the normalizer methods clean and testable.
- **`Retry` utility** — exponential backoff with a configurable exception allowlist is a proper implementation. It just needs to be wired into the providers alongside the rate limiter.
- **`ExternalIds` self-normalizing constructor** — normalizing the DOI at construction time rather than at comparison time is the right design. The issue (finding #15) is that the same logic was duplicated elsewhere, not that the pattern is wrong.
- **Laravel layer isolation** — `src/Laravel/` is entirely optional. The core (`NexusService`, providers, dedup) has no dependency on `illuminate/*` classes. This is the right architecture for a library.
- **Readonly-style constructor promotion** — consistent use of PHP 8 constructor promotion for value objects (`Query`, `SnowballConfig`, `ProviderConfig`, `DeduplicationConfig`) makes the models easy to reason about.

---

## Issue Summary

| # | Severity | File(s) | Issue |
|---|----------|---------|-------|
| 1 | 🔴 Critical | `Providers/BaseProvider.php`, `Utils/RateLimiter.php` | Rate limiter exists but is never called in any provider |
| 2 | 🔴 Critical | `Dedup/`, `README.md` | `AggressiveStrategy` documented but not implemented |
| 3 | 🔴 Critical | `Laravel/NexusSearcher.php`, `Laravel/NexusServiceProvider.php` | Singleton mutation is unsafe under concurrency |
| 4 | 🟠 High | `Laravel/NexusSearcher.php` | Cache key omits `language`, `maxResults`, `offset`, `metadata`; `clearAllCache()` is a no-op |
| 5 | 🟠 High | `cacert.pem`, `Providers/BaseProvider.php` | Stale-prone CA bundle committed to repo |
| 6 | 🟡 Medium | `Core/SnowballService.php` | Hardcoded dedup config in constructor ignores caller's settings |
| 7 | 🟡 Medium | `Dedup/DeduplicationStrategy.php` | `fuseDocuments()` ignores `DeduplicationConfig::$providerPriority` |
| 8 | 🟡 Medium | `CitationAnalysis/CitationGraphBuilder.php` | O(n²) co-citation and bibliographic coupling algorithms |
| 9 | 🟡 Medium | `Laravel/NexusSearcher.php` | `searchAsync()` accesses non-existent `->id` on `PendingDispatch` |
| 10 | 🟡 Medium | `Models/Document.php`, all Providers | `rawData` stored unconditionally, risks large memory usage |
| 11 | 🟢 Minor | `config.yml` | Personal email address committed to source control |
| 12 | 🟢 Minor | `Dedup/ConservativeStrategy.php` | `levenshtein()` / `strlen()` are byte-based, not multibyte-safe |
| 13 | 🟢 Minor | `Models/Query.php` | Weak `uniqid()` ID — collision-prone in rapid-fire use |
| 14 | 🟢 Minor | `composer.json`, `README.md` | Package name mismatch (`nexus/nexus-php` vs `mbsoft31/nexus-php`) |
| 15 | 🟢 Minor | `Models/ExternalIds.php`, `Dedup/DeduplicationStrategy.php`, `Core/SnowballService.php` | DOI normalization duplicated in three places |
| 16 | 🟢 Minor | `Laravel/NexusSearcher.php` | `clearAllCache()` flushes an untagged namespace — always a no-op |

---

## Recommended Fix Order

1. **Wire the rate limiter** (finding #1) — one-line change in `BaseProvider::makeRawRequest()`, prevents API bans.
2. **Fix the concurrency bug** (finding #3) — pre-register providers in the service provider boot; safe for Octane.
3. **Fix the cache key** (finding #4) — include all query fields in the hash; also fix `clearAllCache()`.
4. **Fix `searchAsync()` job ID** (finding #9) — prevents a silent runtime error.
5. **Replace `cacert.pem`** (finding #5) — `composer/ca-bundle` is a one-dependency swap.
6. **Implement or remove `AggressiveStrategy`** (finding #2) — resolve the documentation/code mismatch.
7. **Make `rawData` opt-in** (finding #10) — thread `Query::$includeRawData` through providers.
8. **Fix `fuseDocuments()` priority** (finding #7) — read from config, not a local array.
9. Address remaining minor issues in any order.
