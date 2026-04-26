docs = {}

# ─────────────────────────────────────────────────────────────────────────────
docs["docs/spec-shared-kernel.md"] = '''# Class Specs — Shared Kernel

> **File:** `docs/spec-shared-kernel.md`
> **Namespace root:** `Nexus\\Shared`
> **Rule:** No `Illuminate\\*`. No provider logic. No framework imports.

---

## `WorkIdNamespace` (enum)

**File:** `src/Shared/WorkIdNamespace.php`

```php
enum WorkIdNamespace: string {
    case DOI      = 'doi';
    case ARXIV    = 'arxiv';
    case OPENALEX = 'openalex';
    case S2       = 's2';
    case PUBMED   = 'pubmed';
    case IEEE     = 'ieee';
    case DOAJ     = 'doaj';
}
```

**Rules:**
- This is the exhaustive list of supported ID namespaces.
- No namespace outside this enum is valid in the domain.
- Do not add provider-specific values here unless the provider has a stable, globally meaningful identifier space.

**Tests:**
```
it_covers_all_seven_supported_providers
it_is_backed_by_lowercase_string_values
```

---

## `WorkId` (Value Object)

**File:** `src/Shared/WorkId.php`

```php
final class WorkId
{
    public readonly string $value; // always normalized

    public function __construct(
        public readonly WorkIdNamespace $namespace,
        string $rawValue,
    )

    private static function normalize(WorkIdNamespace $ns, string $raw): string
    public function equals(WorkId $other): bool
    public function toString(): string              // "doi:10.1234/abc"
    public static function fromString(string $s): self
}
```

**Normalization rules (applied in constructor, cannot be bypassed):**
- DOI: strip `https://doi.org/`, `http://dx.doi.org/`, `doi:`, then `strtolower()` and `trim()`
- ARXIV: strip `arxiv:` prefix if present, `strtolower()` and `trim()`
- All others: `strtolower()` and `trim()`

**Invariants:**
- `WorkId` is always in normalized form after construction
- Two `WorkId`s are equal if and only if both namespace and value match
- `fromString('doi:10.1234/X')` must equal `new WorkId(DOI, 'https://doi.org/10.1234/X')`

**Tests:**
```
it_strips_https_doi_org_prefix_from_doi
it_strips_doi_colon_prefix_from_doi
it_lowercases_doi_value
it_strips_arxiv_prefix
it_lowercases_all_namespace_values
it_compares_equal_when_namespace_and_value_match
it_does_not_equal_same_value_different_namespace
it_round_trips_through_toString_and_fromString
it_accepts_doi_without_prefix
it_normalizes_doi_with_mixed_case
```

---

## `WorkIdSet` (Value Object)

**File:** `src/Shared/WorkIdSet.php`

```php
final class WorkIdSet
{
    public function __construct(WorkId ...$ids)

    public static function empty(): self
    public static function fromArray(array $ids): self          // array of WorkId

    public function add(WorkId $id): self                       // returns new instance
    public function findByNamespace(WorkIdNamespace $ns): ?WorkId
    public function primary(): ?WorkId                          // precedence order below
    public function hasOverlapWith(WorkIdSet $other): bool
    public function isEmpty(): bool
    public function count(): int
    public function all(): array                                // WorkId[]
    public function merge(WorkIdSet $other): self               // returns new instance
    public function toString(): string                          // "doi:10.x|arxiv:2301.x"
}
```

**Primary ID precedence order:**
1. DOI
2. OPENALEX
3. S2
4. ARXIV
5. PUBMED
6. IEEE
7. DOAJ

**Invariants:**
- Immutable — `add()` and `merge()` return new instances
- Duplicate namespace entries are allowed (two arXiv versions)
- `hasOverlapWith` compares all pairs, returns true on first match
- Empty set returns `null` from `primary()`

**Tests:**
```
it_returns_doi_as_primary_when_present
it_falls_back_to_openalex_when_doi_absent
it_returns_null_primary_when_empty
it_detects_overlap_via_shared_doi
it_detects_overlap_via_shared_arxiv_id
it_returns_false_overlap_when_no_shared_ids
it_remains_immutable_after_add
it_merges_two_sets_without_removing_duplicates
it_counts_correctly
```

---

## `OrcidId` (Value Object)

**File:** `src/Shared/OrcidId.php`

```php
final class OrcidId
{
    public function __construct(public readonly string $value)
    // throws InvalidArgumentException if format does not match
    // valid format: \\d{4}-\\d{4}-\\d{4}-\\d{3}[\\dX]

    public function equals(OrcidId $other): bool
    public function toString(): string
}
```

**Tests:**
```
it_accepts_valid_orcid_with_x_checksum
it_accepts_valid_orcid_with_digit_checksum
it_rejects_malformed_orcid_too_short
it_rejects_orcid_with_letters_in_wrong_position
```

---

## `Author` (Value Object)

**File:** `src/Shared/Author.php`

```php
final class Author
{
    public function __construct(
        public readonly string   $familyName,
        public readonly ?string  $givenName          = null,
        public readonly ?OrcidId $orcid              = null,
        public readonly ?string  $normalizedFullName = null,
        // pre-computed: lowercase, diacritics stripped
        // if null at construction, compute from familyName + givenName
    )

    public function fullName(): string
    public function hasOrcid(): bool
    public function isSamePerson(Author $other): bool
    // Returns true if both have ORCID and they match,
    // OR if normalized names are identical
}
```

**Tests:**
```
it_returns_full_name_as_given_plus_family
it_returns_family_name_only_when_given_is_null
it_detects_same_person_via_orcid
it_detects_same_person_via_normalized_name
it_returns_false_for_different_authors
it_computes_normalized_name_if_not_provided
```

---

## `AuthorList` (Value Object)

**File:** `src/Shared/AuthorList.php`

```php
final class AuthorList
{
    public function __construct(Author ...$authors)

    public static function empty(): self
    public static function fromArray(array $authors): self

    public function first(): ?Author
    public function last(): ?Author
    public function count(): int
    public function all(): array              // Author[]
    public function get(int $position): ?Author   // 0-indexed
    public function intersect(AuthorList $other): self
    // returns authors matched by ORCID or normalized name
    public function isEmpty(): bool
}
```

**Tests:**
```
it_returns_first_author
it_returns_null_for_empty_list
it_intersects_by_orcid
it_intersects_by_normalized_name
it_returns_empty_list_when_no_shared_authors
it_counts_correctly
```

---

## `Venue` (Value Object)

**File:** `src/Shared/Venue.php`

```php
final class Venue
{
    public function __construct(
        public readonly string  $name,
        public readonly ?string $issn     = null,
        public readonly ?string $type     = null,
        // 'journal' | 'conference' | 'repository' | 'book' | null
        public readonly ?string $publisher = null,
    )

    public function isJournal(): bool
    public function isConference(): bool
}
```

**Tests:**
```
it_identifies_journal_type
it_identifies_conference_type
it_accepts_null_type_without_error
```

---

## `DomainEvent` (interface)

**File:** `src/Shared/DomainEvent.php`

```php
interface DomainEvent
{
    public function occurredAt(): \\DateTimeImmutable;
    public function eventName(): string;   // e.g. "search.query.executed"
}
```

---

## `DomainException` (base)

**File:** `src/Shared/DomainException.php`

```php
abstract class DomainException extends \\RuntimeException {}
```

All context-specific exceptions extend this.

---
'''

# ─────────────────────────────────────────────────────────────────────────────
docs["docs/spec-search-domain.md"] = '''# Class Specs — Search Domain

> **File:** `docs/spec-search-domain.md`
> **Namespace:** `Nexus\\Search\\Domain`
> **Rule:** Zero framework imports. Zero infrastructure. Only domain concepts.

---

## `SearchTerm` (Value Object)

**File:** `src/Search/Domain/SearchTerm.php`

```php
final class SearchTerm
{
    public function __construct(public readonly string $value)
    // throws InvalidSearchTerm if mb_strlen(trim(value)) < 2

    public function equals(SearchTerm $other): bool
}
```

**Tests:**
```
it_rejects_empty_string
it_rejects_single_character_string
it_rejects_whitespace_only_string
it_accepts_two_character_string
it_accepts_multilingual_term
it_trims_whitespace_before_validation
```

---

## `YearRange` (Value Object)

**File:** `src/Search/Domain/YearRange.php`

```php
final class YearRange
{
    public function __construct(
        public readonly ?int $from = null,
        public readonly ?int $to   = null,
    )
    // throws InvalidYearRange if both set and from > to
    // throws InvalidYearRange if year < 1000 or > (current year + 5)

    public static function since(int $year): self
    public static function until(int $year): self
    public static function between(int $from, int $to): self
    public static function unbounded(): self

    public function contains(int $year): bool
    public function isUnbounded(): bool
    public function overlaps(YearRange $other): bool
}
```

**Tests:**
```
it_rejects_inverted_range
it_rejects_year_below_1000
it_accepts_null_from_or_to
it_contains_year_within_range
it_excludes_year_outside_range
it_contains_all_years_when_unbounded
it_detects_overlapping_ranges
```

---

## `LanguageCode` (Value Object)

**File:** `src/Search/Domain/LanguageCode.php`

```php
final class LanguageCode
{
    public function __construct(public readonly string $value)
    // throws InvalidArgumentException if not matching /^[a-z]{2}(-[A-Z]{2})?$/

    public static function english(): self   // 'en'
    public static function french(): self    // 'fr'
    public static function arabic(): self    // 'ar'

    public function equals(LanguageCode $other): bool
}
```

**Tests:**
```
it_accepts_two_letter_iso_code
it_accepts_locale_with_region
it_rejects_uppercase_language_code
it_rejects_numeric_code
it_rejects_three_letter_code
```

---

## `SearchQuery` (Value Object)

**File:** `src/Search/Domain/SearchQuery.php`

```php
final class SearchQuery
{
    public readonly string $id;
    // always 'Q' . bin2hex(random_bytes(5)) — NEVER uniqid()

    public function __construct(
        public readonly SearchTerm     $term,
        public readonly ?YearRange     $yearRange       = null,
        public readonly ?LanguageCode  $language        = null,
        public readonly int            $maxResults      = 100,
        public readonly int            $offset          = 0,
        public readonly bool           $includeRawData  = false,
        ?string                        $id              = null,
    )

    // AUTHORITATIVE cache key — includes all dimensions
    // Agent must NEVER compute a cache key outside this method
    public function cacheKey(array $sortedProviderAliases = []): string

    public function withOffset(int $offset): self      // returns new instance
    public function withMaxResults(int $max): self      // returns new instance
    public function nextPage(): self                    // offset += maxResults
    public function isFirstPage(): bool
}
```

**`cacheKey()` implementation must hash:**
- `term->value`
- `yearRange->from ?? ''`
- `yearRange->to ?? ''`
- `language->value ?? ''`
- `maxResults`
- `offset`
- `implode(',', $sortedProviderAliases)` — must be sorted before joining

**Tests:**
```
it_generates_crypto_random_id
it_does_not_use_uniqid
it_produces_same_cache_key_for_identical_queries
it_produces_different_cache_key_when_language_differs
it_produces_different_cache_key_when_max_results_differs
it_produces_different_cache_key_when_offset_differs
it_produces_different_cache_key_when_providers_differ
it_is_provider_order_insensitive_in_cache_key
it_advances_offset_on_next_page
it_identifies_first_page
```

---

## `ScholarlyWork` (Entity)

**File:** `src/Search/Domain/ScholarlyWork.php`

```php
final class ScholarlyWork
{
    // private constructor — use static factory
    private function __construct(
        private WorkIdSet        $ids,
        private string           $title,
        private AuthorList       $authors,
        private ?int             $year,
        private ?Venue           $venue,
        private ?string          $abstract,
        private ?int             $citedByCount,
        private bool             $isRetracted,
        private string           $sourceProvider,
        private \\DateTimeImmutable $retrievedAt,
        private ?array           $rawData,       // null unless query->includeRawData
    )

    public static function reconstitute(
        WorkIdSet   $ids,
        string      $title,
        string      $sourceProvider,
        ?int        $year                = null,
        ?AuthorList $authors             = null,
        ?Venue      $venue               = null,
        ?string     $abstract            = null,
        ?int        $citedByCount        = null,
        bool        $isRetracted         = false,
        ?array      $rawData             = null,
    ): self

    // Getters
    public function ids(): WorkIdSet
    public function primaryId(): ?WorkId
    public function title(): string
    public function authors(): AuthorList
    public function year(): ?int
    public function venue(): ?Venue
    public function abstract(): ?string
    public function citedByCount(): ?int
    public function isRetracted(): bool
    public function sourceProvider(): string
    public function retrievedAt(): \\DateTimeImmutable
    public function rawData(): ?array

    // Domain behavior
    public function isSameWorkAs(ScholarlyWork $other): bool
    // Returns true if $this->ids->hasOverlapWith($other->ids)

    public function mergeWith(ScholarlyWork $other): self
    // Returns new instance with merged WorkIdSets.
    // Keeps $this fields as base, overlays non-null fields from $other
    // where $this has null. Does NOT overwrite existing fields.

    public function withRawData(array $raw): self
    public function withoutRawData(): self
    public function hasAbstract(): bool
    public function hasVenue(): bool
    public function isPreprint(): bool    // sourceProvider === 'arxiv' || venue->type === 'repository'
    public function completenessScore(): int
    // 0-10 score based on presence of: doi, abstract, venue, authors,
    // year, citedByCount, language. Used for representative election.
}
```

**Invariants:**
- Title must be non-empty string (enforced in factory)
- `rawData` MUST be null if constructed without it — not an empty array
- `mergeWith()` never overwrites a field that is already set on `$this`
- `isSameWorkAs()` delegates entirely to `WorkIdSet::hasOverlapWith()`

**Tests:**
```
it_identifies_same_work_via_shared_doi
it_identifies_same_work_via_shared_openalex_id
it_returns_false_for_works_with_no_shared_ids
it_merges_work_ids_from_both_sides
it_does_not_overwrite_existing_fields_during_merge
it_merges_abstract_from_other_when_own_is_null
it_stores_raw_data_only_when_provided
it_returns_null_raw_data_by_default
it_scores_completeness_higher_with_more_fields
it_is_a_preprint_when_from_arxiv
```

---

## `CorpusSlice` (Aggregate Root)

**File:** `src/Search/Domain/CorpusSlice.php`

```php
final class CorpusSlice
{
    private array $works = [];  // keyed by primary WorkId string

    private function __construct(public readonly CorpusSliceId $id)

    public static function empty(): self
    public static function fromWorks(ScholarlyWork ...$works): self

    public function addWork(ScholarlyWork $work): void
    // Keyed storage: if primary ID exists, merge into existing entry.
    // If no primary ID, fall back to spl_object_hash (should be rare).

    public function contains(ScholarlyWork $work): bool
    // Uses isSameWorkAs() for comparison

    public function findById(WorkId $id): ?ScholarlyWork
    public function findByTitle(string $normalizedTitle): ?ScholarlyWork

    public function count(): int
    public function all(): array                // ScholarlyWork[]
    public function isEmpty(): bool

    public function merge(CorpusSlice $other): self
    // Returns new CorpusSlice. Works already contained are merged via
    // ScholarlyWork::mergeWith(). New works are added directly.

    public function filter(callable $predicate): self
    // e.g., filter out retracted works, filter by year

    public function sortByYear(bool $descending = true): self
    public function sortByCitedByCount(bool $descending = true): self

    public function subtract(CorpusSlice $other): self
    // Returns works in $this not contained in $other

    public function retracted(): self
    // Subset of retracted works

    public function withoutRetracted(): self
}
```

**Invariants:**
- Adding a work that `isSameWorkAs()` an existing work merges rather than duplicates
- `merge()` always returns a new instance

**Tests:**
```
it_starts_empty
it_adds_work_without_duplicating
it_merges_instead_of_duplicating_same_work
it_contains_added_work
it_does_not_contain_work_with_no_shared_ids
it_merges_two_slices_without_duplication
it_returns_correct_count
it_subtracts_known_works
it_filters_by_predicate
it_excludes_retracted_when_asked
```

---

## `CorpusSliceId` (Value Object)

**File:** `src/Search/Domain/CorpusSliceId.php`

```php
final class CorpusSliceId
{
    public function __construct(public readonly string $value)
    public static function generate(): self  // bin2hex(random_bytes(8))
    public function equals(CorpusSliceId $other): bool
}
```

---

## Exceptions

**Files:** `src/Search/Domain/Exceptions/`

```php
final class InvalidSearchTerm   extends \\Nexus\\Shared\\DomainException {}
final class InvalidYearRange    extends \\Nexus\\Shared\\DomainException {}
final class ProviderUnavailable extends \\Nexus\\Shared\\DomainException {
    public function __construct(
        public readonly string $providerAlias,
        string $reason,
        ?\\Throwable $previous = null,
    )
}
```

---

## Domain Events

**Files:** `src/Search/Domain/Events/`

```php
final class SearchQueryExecuted implements DomainEvent
{
    public function __construct(
        public readonly string $queryId,
        public readonly string $providerAlias,
        public readonly int    $resultCount,
        public readonly int    $durationMs,
    )
}

final class ProviderSearchCompleted implements DomainEvent
{
    public function __construct(
        public readonly string      $queryId,
        public readonly string      $providerAlias,
        public readonly CorpusSlice $slice,
    )
}

final class ProviderSearchFailed implements DomainEvent
{
    public function __construct(
        public readonly string $queryId,
        public readonly string $providerAlias,
        public readonly string $reason,
    )
}
```

---
'''

# ─────────────────────────────────────────────────────────────────────────────
docs["docs/spec-search-ports.md"] = '''# Class Specs — Search Ports

> **File:** `docs/spec-search-ports.md`
> **Namespace:** `Nexus\\Search\\Domain\\Ports`

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
'''

# ─────────────────────────────────────────────────────────────────────────────
docs["docs/spec-search-infrastructure.md"] = '''# Class Specs — Search Infrastructure

> **File:** `docs/spec-search-infrastructure.md`
> **Namespace:** `Nexus\\Search\\Infrastructure`
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
        private readonly \\GuzzleHttp\\Client $guzzle,
    )

    public static function create(int $timeoutSeconds = 30): self
    // Uses composer/ca-bundle for SSL verification:
    // verify: \\Composer\\CaBundle\\CaBundle::getSystemCaRootBundlePath()
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
        private readonly \\Illuminate\\Contracts\\Cache\\Repository $cache,
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
'''

# ─────────────────────────────────────────────────────────────────────────────
docs["docs/spec-search-application.md"] = '''# Class Specs — Search Application Services

> **File:** `docs/spec-search-application.md`
> **Namespace:** `Nexus\\Search\\Application`
> **Rule:** No HTTP. No SQL. No framework. Depends only on domain + ports.

---

## `SearchAcrossProviders` (Command)

**File:** `src/Search/Application/SearchAcrossProviders.php`

```php
final class SearchAcrossProviders
{
    public function __construct(
        public readonly SearchQuery $query,
        public readonly array       $providerAliases = [],
        // empty = use all registered providers
    )
}
```

---

## `SearchAcrossProvidersHandler` (Application Service)

**File:** `src/Search/Application/SearchAcrossProvidersHandler.php`

```php
final class SearchAcrossProvidersHandler
{
    public function __construct(
        /** @var AcademicProviderPort[] */
        private readonly array           $providers,
        private readonly SearchCachePort $cache,
        private readonly int             $cacheTtl = 3600,
    )

    /**
     * Execute the search command and return a merged CorpusSlice.
     *
     * Algorithm:
     * 1. Determine which providers to use (all or filtered by $command->providerAliases)
     * 2. Build cache key using $command->query->cacheKey($providerAliases)
     * 3. Return cached result if present
     * 4. For each provider, call $provider->search($command->query)
     *    — collect results, emit ProviderSearchCompleted or ProviderSearchFailed events
     * 5. Build a CorpusSlice from each provider's results (addWork merges same works)
     * 6. Cache the final slice
     * 7. Return the merged CorpusSlice
     *
     * Each provider call is independent:
     * — one provider failing should not stop other providers
     * — failed providers emit ProviderSearchFailed, not an exception
     */
    public function handle(SearchAcrossProviders $command): SearchAcrossProvidersResult
}

final class SearchAcrossProvidersResult
{
    public function __construct(
        public readonly CorpusSlice $corpus,
        public readonly array       $providerResults,
        // ProviderSearchResult[]
        public readonly bool        $fromCache,
        public readonly int         $durationMs,
    )
}

final class ProviderSearchResult
{
    public function __construct(
        public readonly string $providerAlias,
        public readonly int    $resultCount,
        public readonly bool   $success,
        public readonly ?string $error = null,
        public readonly int    $durationMs = 0,
    )
}
```

**Tests:**
```
it_merges_results_from_two_providers
it_returns_cached_result_on_second_call
it_uses_correct_cache_key_with_provider_aliases
it_continues_other_providers_when_one_fails
it_emits_provider_search_completed_for_each_successful_provider
it_emits_provider_search_failed_for_failed_provider
it_returns_from_cache_flag_true_on_cache_hit
it_only_calls_providers_matching_given_aliases
it_returns_empty_corpus_when_all_providers_fail
```

---

## `SearchByWorkId` (Command)

**File:** `src/Search/Application/SearchByWorkId.php`

```php
final class SearchByWorkId
{
    public function __construct(
        public readonly WorkId $id,
        public readonly array  $providerAliases = [],
    )
}
```

---

## `SearchByWorkIdHandler` (Application Service)

**File:** `src/Search/Application/SearchByWorkIdHandler.php`

```php
final class SearchByWorkIdHandler
{
    public function __construct(
        /** @var AcademicProviderPort[] */
        private readonly array $providers,
    )

    /**
     * Try each provider that supports the ID namespace.
     * Return the first successful result.
     * Return null if no provider finds the work.
     */
    public function handle(SearchByWorkId $command): ?ScholarlyWork
}
```

**Tests:**
```
it_returns_work_from_first_supporting_provider
it_skips_providers_that_do_not_support_namespace
it_returns_null_when_no_provider_finds_work
it_tries_providers_in_registration_order
```

---
'''

from pathlib import Path

for name, content in docs.items():
    path = Path(name)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    print(f"wrote {name} ({len(content)} chars, {content.count(chr(10))} lines)")

docs2 = {}

docs2["docs/spec-deduplication.md"] = '''# Class Specs — Deduplication Module

> **File:** `docs/spec-deduplication.md`
> **Namespace:** `Nexus\\Deduplication`
> **Rule:** No framework. No HTTP. Depends on `Nexus\\Search\\Domain` for `ScholarlyWork`.

---

## `DuplicateReason` (enum)

**File:** `src/Deduplication/Domain/DuplicateReason.php`

```php
enum DuplicateReason: string {
    case DOI_MATCH        = 'doi_match';
    case ARXIV_MATCH      = 'arxiv_match';
    case OPENALEX_MATCH   = 'openalex_match';
    case S2_MATCH         = 's2_match';
    case PUBMED_MATCH     = 'pubmed_match';
    case TITLE_FUZZY      = 'title_fuzzy';
    case FINGERPRINT      = 'fingerprint';
    // title-fragment + first-author-family-name + year
}

// Confidence ranges by reason (documentation / enforcement in policy):
// DOI_MATCH      → 1.0  (exact)
// ARXIV_MATCH    → 1.0  (exact)
// OPENALEX_MATCH → 1.0  (exact)
// S2_MATCH       → 1.0  (exact)
// PUBMED_MATCH   → 1.0  (exact)
// TITLE_FUZZY    → 0.70–0.99 (fuzzy ratio / 100)
// FINGERPRINT    → 0.85–0.95 (heuristic)
```

---

## `Duplicate` (Value Object)

**File:** `src/Deduplication/Domain/Duplicate.php`

```php
final class Duplicate
{
    public function __construct(
        public readonly WorkId         $primaryId,   // representative's primary ID
        public readonly WorkId         $secondaryId, // duplicate's primary ID
        public readonly DuplicateReason $reason,
        public readonly float           $confidence, // 0.0 – 1.0
    )

    public function involves(WorkId $id): bool
    public function isHighConfidence(): bool    // confidence >= 0.95
    public function toArray(): array            // for logging/persistence
}
```

---

## `DedupClusterId` (Value Object)

**File:** `src/Deduplication/Domain/DedupClusterId.php`

```php
final class DedupClusterId
{
    public function __construct(public readonly string $value)
    public static function generate(): self   // bin2hex(random_bytes(8))
    public function equals(DedupClusterId $other): bool
}
```

---

## `DedupCluster` (Aggregate Root)

**File:** `src/Deduplication/Domain/DedupCluster.php`

```php
final class DedupCluster
{
    private ScholarlyWork $representative;
    private array $members      = [];   // ScholarlyWork[]
    private array $duplicates   = [];   // Duplicate[]

    private function __construct(
        public readonly DedupClusterId $id,
        ScholarlyWork $seed,
    )

    public static function startWith(ScholarlyWork $seed): self

    public function absorb(ScholarlyWork $work, Duplicate $evidence): void
    // Adds the work to members[], adds the evidence to duplicates[]
    // Does NOT change the representative

    public function representative(): ScholarlyWork

    public function electRepresentative(RepresentativeElectionPort $policy): void
    // Delegates representative selection to policy
    // Stores the result back as $this->representative

    public function members(): array            // ScholarlyWork[] — includes representative
    public function nonRepresentatives(): array // ScholarlyWork[] — excludes representative
    public function duplicateEvidence(): array  // Duplicate[]
    public function size(): int
    public function hasDoi(): bool              // representative has DOI
    public function allDois(): array            // all DOIs from all members (for persistence)
    public function allArxivIds(): array
    public function providerCounts(): array     // ['openalex' => 3, 'arxiv' => 1, ...]
}
```

**Invariants:**
- Cluster always has at least one member (the seed)
- Representative is always a current member
- `electRepresentative()` must be called before using clusters as final output
- `absorb()` is idempotent for the same work

**Tests:**
```
it_starts_with_single_seed_as_representative
it_absorbs_a_duplicate_work
it_size_grows_on_absorb
it_collects_all_dois_from_all_members
it_counts_provider_occurrences
it_elects_most_complete_work_as_representative
it_non_representatives_excludes_elected_work
```

---

## `DedupClusterCollection` (Value Object)

**File:** `src/Deduplication/Domain/DedupClusterCollection.php`

```php
final class DedupClusterCollection
{
    /** @var DedupCluster[] */
    private array $clusters = [];

    public function __construct(DedupCluster ...$clusters)
    public static function empty(): self

    public function add(DedupCluster $cluster): void
    public function count(): int
    public function totalMemberCount(): int
    public function duplicateCount(): int          // total members - cluster count
    public function all(): array                   // DedupCluster[]
    public function representativeCorpus(): CorpusSlice
    // Returns a CorpusSlice of all cluster representatives
    public function findByWorkId(WorkId $id): ?DedupCluster
    // Searches all clusters for a member matching the work ID
}
```

---

## Ports

### `DeduplicationPolicyPort`

**File:** `src/Deduplication/Domain/Ports/DeduplicationPolicyPort.php`

```php
interface DeduplicationPolicyPort
{
    public function name(): string;

    /**
     * Detect duplicates in the given work list.
     * Only returns pairs not already confirmed by a previous (higher-priority) policy.
     * MUST NOT return duplicate entries for the same pair.
     *
     * @param  ScholarlyWork[] $works
     * @return Duplicate[]
     */
    public function detect(array $works): array;
}
```

### `RepresentativeElectionPort`

**File:** `src/Deduplication/Domain/Ports/RepresentativeElectionPort.php`

```php
interface RepresentativeElectionPort
{
    /**
     * Given a list of members, return the best representative.
     * Selection criteria are implementation-specific.
     *
     * @param  ScholarlyWork[] $members
     */
    public function elect(array $members): ScholarlyWork;
}
```

---

## Infrastructure — Policies

### `DoiMatchPolicy`

**File:** `src/Deduplication/Infrastructure/DoiMatchPolicy.php`

```php
final class DoiMatchPolicy implements DeduplicationPolicyPort
{
    public function name(): string  // 'doi_match'

    public function detect(array $works): array
    // Build index: doi_value => work_primary_id
    // For each work with DOI: if doi already in index, emit Duplicate(confidence=1.0)
    // O(n) — one pass, one index
}
```

**Tests:**
```
it_detects_two_works_with_identical_doi
it_normalizes_doi_before_comparing
it_ignores_works_without_doi
it_returns_empty_when_all_dois_are_unique
it_is_O_n_not_O_n_squared
```

### `NamespaceMatchPolicy`

**File:** `src/Deduplication/Infrastructure/NamespaceMatchPolicy.php`

```php
final class NamespaceMatchPolicy implements DeduplicationPolicyPort
{
    public function __construct(
        private readonly WorkIdNamespace $namespace,
    )
    // One policy instance per namespace
    // Instantiate for: ARXIV, OPENALEX, S2, PUBMED separately

    public function name(): string  // e.g. 'arxiv_match'
    public function detect(array $works): array
    // Same O(n) index approach as DoiMatchPolicy
}
```

### `TitleFuzzyPolicy`

**File:** `src/Deduplication/Infrastructure/TitleFuzzyPolicy.php`

```php
final class TitleFuzzyPolicy implements DeduplicationPolicyPort
{
    public function __construct(
        private readonly TitleNormalizer $normalizer,
        private readonly int             $threshold = 92,
        // 0-100; 92 recommended over old default of 97 for better recall
        private readonly int             $maxYearGap = 1,
    )

    public function name(): string  // 'title_fuzzy'

    public function detect(array $works): array
    // Algorithm:
    // 1. Normalize all titles via TitleNormalizer
    // 2. Build a sorted list of (normalizedTitle, workIndex) pairs
    // 3. Compare adjacent pairs in sorted list (small edit distance is likely nearby)
    // 4. For pairs within max_year_gap: compute Unicode-safe ratio
    // 5. Emit Duplicate if ratio >= threshold
    //
    // This is NOT O(n²) across all pairs — sorting + adjacent comparison
    // reduces costly edit-distance calls to near-matches only.
}
```

### `FingerprintPolicy`

**File:** `src/Deduplication/Infrastructure/FingerprintPolicy.php`

```php
final class FingerprintPolicy implements DeduplicationPolicyPort
{
    public function name(): string  // 'fingerprint'

    public function detect(array $works): array
    // Fingerprint = md5(normalizedTitle[0:50] . ':' . normalizedFirstAuthorFamily . ':' . year)
    // Build index: fingerprint => work
    // Collision = high-confidence duplicate (0.90)
    // O(n)
}
```

### `TitleNormalizer`

**File:** `src/Deduplication/Infrastructure/TitleNormalizer.php`

```php
final class TitleNormalizer
{
    public function normalize(string $title): string
    // Steps (in order):
    // 1. mb_strtolower($title, 'UTF-8')
    // 2. Strip HTML entities
    // 3. Transliterate diacritics via iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', ...)
    // 4. Strip non-alphanumeric except spaces (preg_replace with /[^a-z0-9 ]/))
    // 5. Collapse multiple spaces
    // 6. trim()

    public function fuzzyRatio(string $a, string $b): int
    // 1. Normalize both inputs
    // 2. Use mb_str_split() to get character arrays
    // 3. Compute Levenshtein on character arrays (DP, Unicode-safe)
    // 4. Return (1 - dist / max(len(a), len(b))) * 100
    // NEVER use strlen() — always mb_strlen() on UTF-8 content
}
```

**Tests:**
```
it_lowercases_and_strips_diacritics
it_strips_html_entities
it_handles_arabic_title_without_error
it_handles_chinese_title_without_error
it_computes_100_ratio_for_identical_strings
it_computes_0_ratio_for_completely_different_strings
it_computes_high_ratio_for_near_identical_titles
it_is_not_byte_count_based
```

### `UnionFind`

**File:** `src/Deduplication/Infrastructure/UnionFind.php`

```php
final class UnionFind
{
    private array $parent = [];
    private array $rank   = [];

    public function makeSet(string $id): void
    public function find(string $id): string     // returns root with path compression
    public function union(string $a, string $b): void   // by rank
    public function connected(string $a, string $b): bool
    public function groups(): array              // returns array of arrays (each cluster)
    public function groupOf(string $id): array   // members of $id's cluster
}
```

**Tests:**
```
it_groups_transitively_connected_ids
it_finds_root_with_path_compression
it_unions_by_rank
it_returns_correct_groups
it_handles_single_element_clusters
```

### `WorkFuser`

**File:** `src/Deduplication/Infrastructure/WorkFuser.php`

```php
final class WorkFuser
{
    public function __construct(
        private readonly RepresentativeElectionPort $electionPolicy,
    )

    /**
     * Given a cluster of duplicates, produce one merged ScholarlyWork.
     * Provider priority and field completeness determine representative.
     * Uses ScholarlyWork::mergeWith() to combine fields.
     */
    public function fuse(DedupCluster $cluster): ScholarlyWork
}
```

### `CompletenessElectionPolicy`

**File:** `src/Deduplication/Infrastructure/CompletenessElectionPolicy.php`

```php
final class CompletenessElectionPolicy implements RepresentativeElectionPort
{
    public function __construct(
        private readonly array $providerPriority = [
            // configurable — NOT hardcoded to internal array
            // default: openalex=5, crossref=4, s2=3, arxiv=2, pubmed=2, ieee=1, doaj=1
        ],
    )

    public function elect(array $members): ScholarlyWork
    // 1. Score each member: completenessScore() + providerPriority[$sourceProvider]
    // 2. Return member with highest total score
    // 3. Tie-break: prefer DOI presence, then earlier retrieval
}
```

---

## Application Services

### `DeduplicateCorpus` (Command)

**File:** `src/Deduplication/Application/DeduplicateCorpus.php`

```php
final class DeduplicateCorpus
{
    public function __construct(
        public readonly CorpusSlice $corpus,
        public readonly array       $policyAliases = [],
        // empty = use all registered policies in default order
    )
}
```

### `DeduplicateCorpusHandler`

**File:** `src/Deduplication/Application/DeduplicateCorpusHandler.php`

```php
final class DeduplicateCorpusHandler
{
    public function __construct(
        /** @var DeduplicationPolicyPort[] — ordered, exact-match first */
        private readonly array                    $policies,
        private readonly RepresentativeElectionPort $electionPolicy,
    )

    /**
     * Algorithm:
     * 1. Initialize UnionFind with all work primary IDs
     * 2. For each policy (in order): detect duplicates, union pairs in UnionFind
     * 3. Extract groups from UnionFind
     * 4. For each group: create DedupCluster, absorb members, elect representative
     * 5. Return DedupClusterCollection + stats
     */
    public function handle(DeduplicateCorpus $command): DeduplicateCorpusResult
}

final class DeduplicateCorpusResult
{
    public function __construct(
        public readonly DedupClusterCollection $clusters,
        public readonly int                    $inputCount,
        public readonly int                    $uniqueCount,
        public readonly int                    $duplicatesRemoved,
        public readonly array                  $policyStats,
        // ['doi_match' => 12, 'title_fuzzy' => 5, ...]
        public readonly int                    $durationMs,
    )
}
```

**Tests:**
```
it_clusters_two_works_with_same_doi_into_one_cluster
it_clusters_transitively_via_union_find
it_reports_correct_duplicate_count
it_elects_representative_with_highest_completeness
it_runs_exact_policies_before_fuzzy
it_returns_singleton_clusters_for_unique_works
it_handles_empty_corpus
```

---
'''

docs2["docs/spec-citation-network.md"] = '''# Class Specs — Citation Network Module

> **File:** `docs/spec-citation-network.md`
> **Namespace:** `Nexus\\CitationNetwork`
> **Rule:** No framework. No HTTP. Scalable algorithms only — NO O(n²) nested loops.

---

## `CitationGraphType` (enum)

**File:** `src/CitationNetwork/Domain/CitationGraphType.php`

```php
enum CitationGraphType: string {
    case CITATION               = 'citation';
    case CO_CITATION            = 'co_citation';
    case BIBLIOGRAPHIC_COUPLING = 'bibliographic_coupling';
}
```

---

## `CitationLink` (Value Object)

**File:** `src/CitationNetwork/Domain/CitationLink.php`

```php
final class CitationLink
{
    public function __construct(
        public readonly WorkId $citing,
        public readonly WorkId $cited,
        public readonly float  $weight = 1.0,
    )

    public function involves(WorkId $id): bool
    public function equals(CitationLink $other): bool
    public function reversed(): self   // swap citing ↔ cited
}
```

---

## `CitationGraphId` (Value Object)

**File:** `src/CitationNetwork/Domain/CitationGraphId.php`

```php
final class CitationGraphId
{
    public function __construct(public readonly string $value)
    public static function generate(): self   // bin2hex(random_bytes(8))
    public function equals(CitationGraphId $other): bool
}
```

---

## `CitationGraph` (Aggregate Root)

**File:** `src/CitationNetwork/Domain/CitationGraph.php`

```php
final class CitationGraph
{
    /** @var array<string, ScholarlyWork> key = WorkId::toString() */
    private array $nodes = [];
    /** @var CitationLink[] */
    private array $edges = [];

    private function __construct(
        public readonly CitationGraphId   $id,
        public readonly CitationGraphType $type,
    )

    public static function create(CitationGraphType $type): self
    public static function withId(CitationGraphId $id, CitationGraphType $type): self

    public function addWork(ScholarlyWork $work): void
    // Keyed by $work->primaryId()->toString()
    // Idempotent — re-adding the same work is a no-op

    /**
     * Record that $citing cites $cited.
     *
     * @throws WorkNotInGraph if $citing is not in this graph
     * IMPORTANT: $cited need NOT be in the graph (external citation)
     * but $citing MUST be — this is the invariant that prevents dangling source edges.
     */
    public function recordCitation(WorkId $citing, WorkId $cited): void

    // Graph traversal
    public function citedBy(WorkId $id): array       // WorkId[] — works that cite $id
    public function cites(WorkId $id): array          // WorkId[] — works cited by $id
    public function hasWork(WorkId $id): bool
    public function inDegree(WorkId $id): int         // how many times $id is cited
    public function outDegree(WorkId $id): int        // how many citations $id makes

    // Accessors
    public function nodeCount(): int
    public function edgeCount(): int
    public function allWorks(): array                 // ScholarlyWork[]
    public function allEdges(): array                 // CitationLink[]
    public function workByIdString(string $s): ?ScholarlyWork
}
```

**Invariants:**
- `recordCitation()` throws `WorkNotInGraph` if `$citing` not present
- Edges are idempotent — recording the same pair twice is a no-op
- Removing works is not supported (graphs are append-only)

**Tests:**
```
it_adds_works_idempotently
it_records_citation_when_citing_work_exists
it_throws_when_citing_work_not_in_graph
it_allows_citation_to_external_work_not_in_graph
it_reports_in_degree_correctly
it_reports_out_degree_correctly
it_returns_works_that_cite_a_given_id
it_returns_works_cited_by_a_given_id
it_is_idempotent_for_duplicate_edges
```

---

## `SnowballConfig` (Value Object)

**File:** `src/CitationNetwork/Domain/SnowballConfig.php`

```php
final class SnowballConfig
{
    public function __construct(
        public readonly bool $forward       = true,
        public readonly bool $backward      = true,
        public readonly int  $depth         = 1,
        public readonly int  $maxCitations  = 100,
        public readonly int  $maxReferences = 100,
    )
    // throws SnowballDepthExceeded if depth < 1 or depth > 5

    public static function forwardOnly(int $depth = 1): self
    public static function backwardOnly(int $depth = 1): self
    public static function bidirectional(int $depth = 1): self
    public function canGoDeeper(int $currentDepth): bool
}
```

**Tests:**
```
it_rejects_depth_zero
it_rejects_depth_greater_than_five
it_accepts_depth_one_through_five
it_reports_can_go_deeper_correctly
```

---

## `SnowballRoundId` (Value Object)

```php
final class SnowballRoundId
{
    public function __construct(public readonly string $value)
    public static function generate(): self
}
```

---

## `SnowballRound` (Entity)

**File:** `src/CitationNetwork/Domain/SnowballRound.php`

```php
final class SnowballRound
{
    private function __construct(
        public readonly SnowballRoundId $id,
        public readonly int             $depth,
        private readonly CorpusSlice    $newWorks,
        private readonly int            $totalDiscovered,
        private readonly int            $alreadyKnown,
    )

    public static function compute(
        CorpusSlice $existingCorpus,
        CorpusSlice $discovered,
        int         $depth,
    ): self
    // Partitions discovered into:
    //   newWorks     = discovered works not in existingCorpus
    //   alreadyKnown = discovered works already in existingCorpus

    public function newWorks(): CorpusSlice
    public function newWorkCount(): int
    public function alreadyKnownCount(): int
    public function totalDiscovered(): int
    public function isEmpty(): bool       // newWorkCount() === 0
    public function convergenceRatio(): float
    // alreadyKnown / totalDiscovered — high value means snowball converging
}
```

**Tests:**
```
it_partitions_new_from_already_known
it_counts_total_discovered_correctly
it_is_empty_when_all_discovered_already_known
it_computes_correct_convergence_ratio
it_new_works_count_matches_unknown_works
```

---

## `InfluentialWork` (Value Object)

**File:** `src/CitationNetwork/Domain/InfluentialWork.php`

```php
final class InfluentialWork
{
    public function __construct(
        public readonly WorkId $workId,
        public readonly float  $pageRankScore,
        public readonly int    $inDegree,
        public readonly int    $outDegree,
        public readonly ?int   $kCore = null,
    )

    public function isMoreInfluentialThan(InfluentialWork $other): bool
    public function hubScore(): float    // outDegree / (inDegree + outDegree + 1)
    public function authorityScore(): float  // inDegree / (inDegree + outDegree + 1)
}
```

---

## `NetworkMetrics` (Value Object)

**File:** `src/CitationNetwork/Domain/NetworkMetrics.php`

```php
final class NetworkMetrics
{
    public function __construct(
        /** @var array<string, float> workIdString => pageRank */
        public readonly array $pageRank    = [],
        /** @var array<string, int> workIdString => in-degree */
        public readonly array $inDegree    = [],
        /** @var array<string, int> workIdString => k-core number */
        public readonly array $kCore       = [],
        public readonly float $density     = 0.0,
        public readonly float $avgClustering = 0.0,
    )

    public function influentialWorks(int $topN = 20): array   // InfluentialWork[]
    public function pageRankOf(WorkId $id): float
    public function kCoreOf(WorkId $id): ?int
    public function toArray(): array   // for JSON persistence in citation_graphs.metadata
}
```

---

## Ports

### `SnowballingProviderPort`

```php
interface SnowballingProviderPort
{
    public function alias(): string;

    /**
     * @return ScholarlyWork[] Works that cite $work
     */
    public function getCitingWorks(ScholarlyWork $work, int $limit): array;

    /**
     * @return ScholarlyWork[] Works referenced/cited by $work
     */
    public function getReferencedWorks(ScholarlyWork $work, int $limit): array;

    public function supportsForward(): bool;
    public function supportsBackward(): bool;
}
```

OpenAlex and SemanticScholar will implement this.
ArXiv will NOT implement this — it doesn\'t provide citation data.

### `CitationGraphRepositoryPort`

```php
interface CitationGraphRepositoryPort
{
    public function save(CitationGraph $graph): void;
    public function findById(CitationGraphId $id): ?CitationGraph;
    /** @return CitationGraph[] */
    public function findByProjectId(string $projectId): array;
    public function delete(CitationGraphId $id): void;
}
```

### `GraphAlgorithmPort`

```php
interface GraphAlgorithmPort
{
    /**
     * Compute metrics for the given graph.
     * Implementations must be efficient for graphs of 1k–50k nodes.
     */
    public function compute(CitationGraph $graph): NetworkMetrics;
}
```

---

## Infrastructure — Algorithms

### `PageRankCalculator`

**File:** `src/CitationNetwork/Infrastructure/Algorithms/PageRankCalculator.php`

```php
final class PageRankCalculator implements GraphAlgorithmPort
{
    public function __construct(
        private readonly float $dampingFactor  = 0.85,
        private readonly int   $maxIterations  = 100,
        private readonly float $convergence    = 1e-6,
    )

    public function compute(CitationGraph $graph): NetworkMetrics
    // Standard iterative PageRank:
    // 1. Initialize all scores = 1/N
    // 2. Iterate: PR(i) = (1-d)/N + d * sum(PR(j)/OutDegree(j)) for j in citedBy(i)
    // 3. Repeat until max diff < convergence or maxIterations reached
    // Handle dangling nodes (0 out-degree) by distributing their rank equally
}
```

### `InvertedIndexCoCitation`

**File:** `src/CitationNetwork/Infrastructure/Algorithms/InvertedIndexCoCitation.php`

```php
final class InvertedIndexCoCitation
{
    /**
     * Build a co-citation graph using an inverted index.
     * COMPLEXITY: O(n * k) where k = avg out-degree — NOT O(n²)
     *
     * Algorithm:
     * 1. Build index: cited_work_id => [citing_work_id, ...]
     * 2. For each cited work A:
     *      For each citing_work C that cites A:
     *          For each other_cited_work B in C's reference list:
     *              co_citation_counts[A][B]++
     * 3. Build CitationGraph(CO_CITATION) from co_citation_counts
     *    Edge A→B weight = number of works that cite both A and B
     *
     * MUST NOT use nested pairwise O(n²) loops.
     */
    public function build(CitationGraph $citationGraph): CitationGraph
}
```

### `InvertedIndexBibCoupling`

**File:** `src/CitationNetwork/Infrastructure/Algorithms/InvertedIndexBibCoupling.php`

```php
final class InvertedIndexBibCoupling
{
    /**
     * Build a bibliographic coupling graph using an inverted index.
     * COMPLEXITY: O(n * k) — NOT O(n²)
     *
     * Algorithm:
     * 1. Build index: reference_id => [work_ids_that_cite_it, ...]
     * 2. For each reference R:
     *      For each pair (A, B) of works that both cite R:
     *          coupling_counts[A][B]++
     * 3. Build CitationGraph(BIBLIOGRAPHIC_COUPLING) from coupling_counts
     *    Edge A-B weight = number of shared references
     */
    public function build(CitationGraph $citationGraph): CitationGraph
}
```

### `KCoreDecomposer`

**File:** `src/CitationNetwork/Infrastructure/Algorithms/KCoreDecomposer.php`

```php
final class KCoreDecomposer
{
    /**
     * Compute k-core decomposition for a citation graph.
     * Returns map: workId => core number
     *
     * Algorithm: iterative degree-based pruning
     * 1. Compute in-degree for all nodes
     * 2. For k = 1, 2, ...:
     *    Remove all nodes with current degree < k
     *    Update degrees of neighbors
     * 3. Core number = highest k for which a node survived
     */
    public function decompose(CitationGraph $graph): array  // WorkId string => int
}
```

### `BfsShortestPath`

**File:** `src/CitationNetwork/Infrastructure/Algorithms/BfsShortestPath.php`

```php
final class BfsShortestPath
{
    /**
     * Find shortest directed path from $source to $target in a CitationGraph.
     * Returns null if no path exists.
     *
     * @return WorkId[]|null  ordered list from source to target, inclusive
     */
    public function find(
        CitationGraph $graph,
        WorkId        $source,
        WorkId        $target,
    ): ?array
}
```

---

## Application Services

### `RunSnowballHandler`

**File:** `src/CitationNetwork/Application/RunSnowballHandler.php`

```php
final class RunSnowballHandler
{
    public function __construct(
        /** @var SnowballingProviderPort[] */
        private readonly array           $providers,
        private readonly DeduplicateCorpusHandler $deduplicator,
    )

    /**
     * Algorithm:
     * 1. Start with seed corpus
     * 2. For each depth level up to config->depth:
     *    a. For each work in current corpus:
     *       — if forward: call provider->getCitingWorks() → collect
     *       — if backward: call provider->getReferencedWorks() → collect
     *    b. Deduplicate discovered set
     *    c. Compute SnowballRound (new vs already-known)
     *    d. If round isEmpty: break early (converged)
     *    e. Add new works to cumulative corpus
     * 3. Return all rounds + final corpus
     */
    public function handle(RunSnowball $command): RunSnowballResult
}

final class RunSnowball
{
    public function __construct(
        public readonly CorpusSlice   $seedCorpus,
        public readonly SnowballConfig $config,
        public readonly array          $providerAliases = [],
    )
}

final class RunSnowballResult
{
    public function __construct(
        public readonly CorpusSlice    $finalCorpus,
        public readonly array          $rounds,          // SnowballRound[]
        public readonly int            $totalNewWorks,
        public readonly bool           $converged,       // last round was empty
        public readonly int            $durationMs,
    )
}
```

**Tests:**
```
it_expands_corpus_by_one_round
it_stops_early_when_no_new_works_found
it_deduplicates_discovered_works
it_respects_forward_only_config
it_respects_backward_only_config
it_respects_max_depth
it_returns_convergence_flag
it_skips_providers_not_in_aliases
```

### `BuildCitationGraphHandler`

**File:** `src/CitationNetwork/Application/BuildCitationGraphHandler.php`

```php
final class BuildCitationGraphHandler
{
    public function __construct(
        /** @var SnowballingProviderPort[] */
        private readonly array                      $providers,
        private readonly CitationGraphRepositoryPort $repository,
    )

    public function handle(BuildCitationGraph $command): CitationGraph
    // 1. Create CitationGraph(CITATION)
    // 2. Add all corpus works as nodes
    // 3. For each work: fetch references → recordCitation for each
    // 4. Save to repository
    // 5. Return graph
}
```

### `AnalyzeNetworkHandler`

**File:** `src/CitationNetwork/Application/AnalyzeNetworkHandler.php`

```php
final class AnalyzeNetworkHandler
{
    public function __construct(
        private readonly CitationGraphRepositoryPort $repository,
        /** @var GraphAlgorithmPort[] */
        private readonly array                       $algorithms,
    )

    public function handle(AnalyzeNetwork $command): NetworkMetrics
    // 1. Load graph from repository
    // 2. Run each algorithm and merge metrics
    // 3. Return combined NetworkMetrics
}
```

---
'''

for name, content in docs2.items():
    path = Path(name)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    print(f"wrote {name} ({len(content)} chars, {content.count(chr(10))} lines)")

docs3 = {}

docs3["docs/spec-dissemination.md"] = '''# Class Specs — Dissemination Module

> **File:** `docs/spec-dissemination.md`
> **Namespace:** `Nexus\\Dissemination`
> **Rule:** No framework. Serializers are pure. Storage is abstract. No domain logic.

---

## Enums

**File:** `src/Dissemination/Domain/BibliographyFormat.php`

```php
enum BibliographyFormat: string {
    case BIBTEX = 'bibtex';
    case RIS    = 'ris';
    case CSV    = 'csv';
    case JSON   = 'json';
    case JSONL  = 'jsonl';
}
```

**File:** `src/Dissemination/Domain/NetworkFormat.php`

```php
enum NetworkFormat: string {
    case GEXF      = 'gexf';
    case GRAPHML   = 'graphml';
    case CYTOSCAPE = 'cytoscape';
}
```

**File:** `src/Dissemination/Domain/FullTextStatus.php`

```php
enum FullTextStatus: string {
    case PENDING   = 'pending';
    case FOUND     = 'found';
    case NOT_FOUND = 'not_found';
    case FAILED    = 'failed';
}
```

---

## `FullText` (Value Object)

**File:** `src/Dissemination/Domain/FullText.php`

```php
final class FullText
{
    private function __construct(
        public readonly WorkId          $workId,
        public readonly FullTextStatus  $status,
        public readonly ?string         $filePath    = null,
        public readonly ?int            $fileSizeBytes = null,
        public readonly ?string         $sourceName  = null,
        // 'arxiv' | 'openalex' | 'semantic_scholar' | 'direct'
        public readonly ?string         $errorMessage = null,
        public readonly ?\\DateTimeImmutable $fetchedAt = null,
    )

    public static function found(
        WorkId $id,
        string $filePath,
        int    $fileSizeBytes,
        string $sourceName,
    ): self

    public static function notFound(WorkId $id, string $sourceName): self

    public static function failed(WorkId $id, string $sourceName, string $reason): self

    public function isFound(): bool
    public function toArray(): array   // for persistence in pdf_fetches table
}
```

---

## Ports

### `BibliographySerializerPort`

**File:** `src/Dissemination/Domain/Ports/BibliographySerializerPort.php`

```php
interface BibliographySerializerPort
{
    public function format(): BibliographyFormat;

    /**
     * Serialize a list of works into the target format string.
     * MUST be pure and deterministic — same input produces same output.
     * MUST NOT access filesystem, database, or network.
     *
     * @param  ScholarlyWork[] $works
     */
    public function serialize(array $works): string;
}
```

### `NetworkSerializerPort`

**File:** `src/Dissemination/Domain/Ports/NetworkSerializerPort.php`

```php
interface NetworkSerializerPort
{
    public function format(): NetworkFormat;

    /**
     * Serialize a CitationGraph into the target format string.
     * Optionally include pre-computed metrics as node attributes.
     * MUST be pure and deterministic.
     */
    public function serialize(
        CitationGraph   $graph,
        ?NetworkMetrics $metrics = null,
    ): string;
}
```

### `FullTextSourcePort`

**File:** `src/Dissemination/Domain/Ports/FullTextSourcePort.php`

```php
interface FullTextSourcePort
{
    public function name(): string;
    // 'arxiv' | 'openalex' | 'semantic_scholar' | 'direct'

    /**
     * Fast pre-check: can this source possibly serve this work?
     * Used to skip sources that have no chance of succeeding.
     * E.g., ArXivPdfSource returns false if work has no arXiv ID.
     */
    public function supports(ScholarlyWork $work): bool;

    /**
     * Attempt to resolve and download the PDF.
     * Returns null if this source cannot serve the work (soft fail).
     * Returns FullText::failed() on network errors (hard fail, still logged).
     * Returns FullText::found() on success.
     *
     * Downloads file to $outputDirectory/{workId}.pdf
     * MUST call rateLimiter->waitForToken() before HTTP request.
     */
    public function fetch(
        ScholarlyWork $work,
        string        $outputDirectory,
    ): ?FullText;
}
```

### `FileStoragePort`

**File:** `src/Dissemination/Domain/Ports/FileStoragePort.php`

```php
interface FileStoragePort
{
    public function write(string $path, string $contents): void;
    public function writeBinary(string $path, string $binary): void;
    public function read(string $path): string;
    public function readBinary(string $path): string;
    public function exists(string $path): bool;
    public function delete(string $path): bool;
    public function size(string $path): int;      // bytes
    public function url(string $path): string;    // public-facing URL
    public function makeDirectory(string $path): void;
}
```

---

## Infrastructure — Serializers

### `BibTexSerializer`

**File:** `src/Dissemination/Infrastructure/Serializers/BibTexSerializer.php`

```php
final class BibTexSerializer implements BibliographySerializerPort
{
    public function format(): BibliographyFormat  // BIBTEX

    public function serialize(array $works): string
    // For each work:
    //   1. Determine entry type: @article, @inproceedings, @misc, @preprint
    //   2. Build citation key: {firstAuthorFamily}{year}{firstTitleWord}
    //      e.g. "smith2023attention"
    //   3. Output BibTeX fields:
    //      title    = {ScholarlyWork::title()}
    //      author   = {Family1, Given1 and Family2, Given2}
    //      year     = {year}
    //      journal  = {venue->name}   (for @article)
    //      booktitle= {venue->name}   (for @inproceedings)
    //      doi      = {ids->findByNamespace(DOI)->value}
    //      url      = {url if no doi}
    //      note     = {RETRACTED} if isRetracted
    //   4. Escape special BibTeX chars: & % $ # _ { } ~ ^ \\
    //   5. Works without year use "nodate"
    //   6. Authors with only family name: output family name alone

    private function entryType(ScholarlyWork $work): string
    private function citationKey(ScholarlyWork $work): string
    private function escapeLatex(string $s): string
    private function formatAuthors(AuthorList $authors): string
}
```

**Tests:**
```
it_produces_at_article_for_journal_work
it_produces_at_misc_for_preprint
it_formats_multiple_authors_with_and_separator
it_escapes_ampersands_in_title
it_escapes_special_bibtex_chars
it_includes_doi_when_present
it_omits_doi_field_when_absent
it_adds_retracted_note
it_uses_nodate_when_year_absent
it_produces_deterministic_output
```

### `RisSerializer`

**File:** `src/Dissemination/Infrastructure/Serializers/RisSerializer.php`

```php
final class RisSerializer implements BibliographySerializerPort
{
    public function format(): BibliographyFormat  // RIS

    public function serialize(array $works): string
    // RIS format per work:
    //   TY  - JOUR (or CONF, GEN)
    //   TI  - {title}
    //   AU  - {Family, Given}  (one line per author)
    //   PY  - {year}
    //   JO  - {venue->name}
    //   DO  - {doi}
    //   UR  - {url}
    //   AB  - {abstract}
    //   ER  -             (end of record — mandatory blank line after)

    private function risType(ScholarlyWork $work): string
}
```

### `GexfSerializer`

**File:** `src/Dissemination/Infrastructure/Serializers/GexfSerializer.php`

```php
final class GexfSerializer implements NetworkSerializerPort
{
    public function format(): NetworkFormat  // GEXF

    public function serialize(CitationGraph $graph, ?NetworkMetrics $metrics = null): string
    // GEXF 1.3 XML:
    // <gexf>
    //   <graph defaultedgetype="directed">
    //     <attributes class="node">
    //       <attribute id="title" title="Title" type="string"/>
    //       <attribute id="year" title="Year" type="integer"/>
    //       <attribute id="pagerank" title="PageRank" type="float"/>
    //       <attribute id="kcore" title="K-Core" type="integer"/>
    //     </attributes>
    //     <nodes>
    //       <node id="{workId}" label="{title}">
    //         <attvalues>...</attvalues>
    //       </node>
    //     </nodes>
    //     <edges>
    //       <edge id="{i}" source="{citing}" target="{cited}" weight="{w}"/>
    //     </edges>
    //   </graph>
    // </gexf>
    // If $metrics provided: include pagerank and kcore as node attributes
    // Output must be valid XML (use DOMDocument, not string concatenation)
}
```

### `CytoscapeSerializer`

**File:** `src/Dissemination/Infrastructure/Serializers/CytoscapeSerializer.php`

```php
final class CytoscapeSerializer implements NetworkSerializerPort
{
    public function format(): NetworkFormat  // CYTOSCAPE

    public function serialize(CitationGraph $graph, ?NetworkMetrics $metrics = null): string
    // Cytoscape.js JSON:
    // {
    //   "elements": {
    //     "nodes": [
    //       { "data": { "id": "doi:10.x", "label": "Title...", "year": 2023,
    //                   "pagerank": 0.02, "kcore": 3 } }
    //     ],
    //     "edges": [
    //       { "data": { "id": "e0", "source": "doi:10.x", "target": "doi:10.y",
    //                   "weight": 1.0 } }
    //     ]
    //   }
    // }
}
```

---

## Infrastructure — PDF Sources

### `CompositePdfSource`

**File:** `src/Dissemination/Infrastructure/PdfSources/CompositePdfSource.php`

```php
final class CompositePdfSource
{
    public function __construct(
        /** @var FullTextSourcePort[] — tried in order */
        private readonly array $sources,
    )

    /**
     * Try sources sequentially, return first FullText::found() result.
     * Collect all attempts for logging regardless of outcome.
     *
     * @return FullText[]  — one per attempted source (all attempts, not just winner)
     */
    public function fetchAll(ScholarlyWork $work, string $outputDir): array

    /**
     * Try sources sequentially, return the first success.
     * Returns FullText::notFound() if all sources fail.
     */
    public function fetchFirst(ScholarlyWork $work, string $outputDir): FullText
}
```

### `ArXivPdfSource`

**File:** `src/Dissemination/Infrastructure/PdfSources/ArXivPdfSource.php`

```php
final class ArXivPdfSource implements FullTextSourcePort
{
    public function __construct(
        private readonly HttpClientPort  $http,
        private readonly RateLimiterPort $rateLimiter,
    )

    public function name(): string  // 'arxiv'

    public function supports(ScholarlyWork $work): bool
    // Returns true only if work has a WorkId(ARXIV, ...)

    public function fetch(ScholarlyWork $work, string $outputDir): ?FullText
    // URL: https://arxiv.org/pdf/{arxivId}.pdf
    // Downloads binary, saves to $outputDir/{arxivId}.pdf
    // Returns FullText::found() on 200, FullText::failed() on error
}
```

### `OpenAlexPdfSource`

**File:** `src/Dissemination/Infrastructure/PdfSources/OpenAlexPdfSource.php`

```php
final class OpenAlexPdfSource implements FullTextSourcePort
{
    public function name(): string  // 'openalex'

    public function supports(ScholarlyWork $work): bool
    // Returns true if work has OPENALEX id

    public function fetch(ScholarlyWork $work, string $outputDir): ?FullText
    // 1. GET /works/{openAlexId} → look for open_access.oa_url
    // 2. If oa_url exists and is PDF: download it
    // 3. If no oa_url: return null (soft fail, try next source)
}
```

---

## Application Services

### `ExportBibliographyHandler`

**File:** `src/Dissemination/Application/ExportBibliographyHandler.php`

```php
final class ExportBibliographyHandler
{
    public function __construct(
        /** @var BibliographySerializerPort[] keyed by format->value */
        private readonly array           $serializers,
        private readonly FileStoragePort $storage,
    )

    public function handle(ExportBibliography $command): ExportBibliographyResult
    // 1. Look up serializer for $command->format
    // 2. Call $serializer->serialize($command->works)
    // 3. Write to $command->outputPath via $storage->write()
    // 4. Return result with path and byte count
}

final class ExportBibliography
{
    public function __construct(
        public readonly array               $works,    // ScholarlyWork[]
        public readonly BibliographyFormat  $format,
        public readonly string              $outputPath,
    )
}

final class ExportBibliographyResult
{
    public function __construct(
        public readonly string             $filePath,
        public readonly int                $workCount,
        public readonly int                $fileSizeBytes,
        public readonly BibliographyFormat $format,
    )
}
```

### `RetrieveFullTextHandler`

**File:** `src/Dissemination/Application/RetrieveFullTextHandler.php`

```php
final class RetrieveFullTextHandler
{
    public function __construct(
        private readonly CompositePdfSource $source,
        private readonly FileStoragePort    $storage,
    )

    public function handle(RetrieveFullText $command): RetrieveFullTextResult
    // 1. Call $source->fetchAll($command->work, $command->outputDir)
    // 2. Return all attempts (caller persists pdf_fetches rows from these)

    public function handleBatch(RetrieveFullTextBatch $command): array
    // Returns RetrieveFullTextResult[] — one per work
    // Continues on individual failure
}

final class RetrieveFullText
{
    public function __construct(
        public readonly ScholarlyWork $work,
        public readonly string        $outputDir,
    )
}

final class RetrieveFullTextResult
{
    public function __construct(
        public readonly WorkId $workId,
        /** @var FullText[] one per attempted source */
        public readonly array  $attempts,
        public readonly bool   $success,
    )

    public function successfulFetch(): ?FullText
    // Returns the FullText::found() entry if any, or null
}
```

**Tests:**
```
it_returns_found_on_first_successful_source
it_tries_all_sources_when_first_fails
it_records_all_attempts_regardless_of_outcome
it_returns_not_found_when_all_sources_fail
it_skips_sources_that_do_not_support_work
```

---
'''

docs3["docs/spec-laravel.md"] = '''# Class Specs — Laravel Integration Layer

> **File:** `docs/spec-laravel.md`
> **Namespace:** `Nexus\\Laravel`
> **Rule:** This layer may use all Illuminate classes freely.
> **Rule:** This layer MUST NOT contain domain logic.
> **Rule:** Provider registry MUST be built once — never mutated per request.

---

## `NexusServiceProvider`

**File:** `src/Laravel/NexusServiceProvider.php`

```php
final class NexusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind ProviderConfig instances from config file
        // Bind concrete infrastructure classes to ports
        // Register all AcademicProviderPort implementations
        // Register all DeduplicationPolicyPort implementations
        // Register all SnowballingProviderPort implementations
        // Register all BibliographySerializerPort implementations
        // Register all NetworkSerializerPort implementations
        // Register all FullTextSourcePort implementations in fetch order

        // CRITICAL: Build the full provider list ONCE here.
        // Never call clearProviders() or registerProvider() elsewhere.
        // Use tagged bindings for collection injection.

        $this->app->singleton(SearchAcrossProvidersHandler::class, function ($app) {
            return new SearchAcrossProvidersHandler(
                providers: $app->tagged(AcademicProviderPort::class),
                cache:     $app->make(SearchCachePort::class),
                cacheTtl:  config(\'nexus.cache.ttl\', 3600),
            );
        });

        $this->app->singleton(DeduplicateCorpusHandler::class, function ($app) {
            return new DeduplicateCorpusHandler(
                policies:       $app->tagged(DeduplicationPolicyPort::class),
                electionPolicy: $app->make(RepresentativeElectionPort::class),
            );
        });

        $this->app->singleton(RunSnowballHandler::class, function ($app) {
            return new RunSnowballHandler(
                providers:    $app->tagged(SnowballingProviderPort::class),
                deduplicator: $app->make(DeduplicateCorpusHandler::class),
            );
        });

        $this->app->singleton(CompositePdfSource::class, function ($app) {
            return new CompositePdfSource(
                sources: $app->tagged(FullTextSourcePort::class),
            );
        });

        $this->app->singleton(SearchCachePort::class, LaravelSearchCache::class);
        $this->app->singleton(FileStoragePort::class, LocalFileStorage::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . \'/Config/nexus.php\' => config_path(\'nexus.php\'),
        ], \'nexus-config\');

        $this->loadMigrationsFrom(__DIR__ . \'/Migrations\');

        if ($this->app->runningInConsole()) {
            $this->commands([
                NexusSearchCommand::class,
                NexusSnowballCommand::class,
                NexusDedupCommand::class,
                NexusFetchPdfCommand::class,
                NexusExportCommand::class,
            ]);
        }
    }
}
```

---

## `nexus.php` Config File

**File:** `src/Laravel/Config/nexus.php`

```php
return [
    \'providers\' => [
        \'openalex\' => [
            \'enabled\'        => true,
            \'rate_per_second\' => 10.0,
            \'timeout\'        => 30,
            \'mailto\'         => env(\'NEXUS_MAILTO\'),  // NEVER hardcode email
        ],
        \'semantic_scholar\' => [
            \'enabled\'        => true,
            \'rate_per_second\' => env(\'NEXUS_S2_API_KEY\') ? 10.0 : 1.0,
            \'api_key\'        => env(\'NEXUS_S2_API_KEY\'),
            \'timeout\'        => 30,
        ],
        \'arxiv\' => [
            \'enabled\'        => true,
            \'rate_per_second\' => 3.0,
            \'timeout\'        => 30,
        ],
        \'crossref\' => [
            \'enabled\'        => true,
            \'rate_per_second\' => 50.0,
            \'mailto\'         => env(\'NEXUS_MAILTO\'),
            \'timeout\'        => 30,
        ],
        \'pubmed\' => [
            \'enabled\'        => env(\'NEXUS_PUBMED_KEY\') !== null,
            \'rate_per_second\' => 3.0,
            \'api_key\'        => env(\'NEXUS_PUBMED_KEY\'),
            \'timeout\'        => 30,
        ],
        \'ieee\' => [
            \'enabled\'        => env(\'NEXUS_IEEE_KEY\') !== null,
            \'rate_per_second\' => 1.0,
            \'api_key\'        => env(\'NEXUS_IEEE_KEY\'),
            \'timeout\'        => 30,
        ],
        \'doaj\' => [
            \'enabled\'        => true,
            \'rate_per_second\' => 2.0,
            \'timeout\'        => 30,
        ],
    ],

    \'deduplication\' => [
        \'policies\'   => [\'doi_match\', \'arxiv_match\', \'openalex_match\',
                         \'s2_match\', \'pubmed_match\', \'title_fuzzy\', \'fingerprint\'],
        \'threshold\'  => 92,
        \'max_year_gap\' => 1,
        \'provider_priority\' => [
            \'openalex\' => 5, \'crossref\' => 4, \'semantic_scholar\' => 3,
            \'arxiv\' => 2, \'pubmed\' => 2, \'ieee\' => 1, \'doaj\' => 1,
        ],
    ],

    \'cache\' => [
        \'enabled\' => true,
        \'ttl\'     => 3600,  // seconds
        \'store\'   => env(\'NEXUS_CACHE_STORE\', \'file\'),
    ],

    \'pdf\' => [
        \'output_directory\' => storage_path(\'nexus/pdfs\'),
        \'sources\'          => [\'arxiv\', \'openalex\', \'semantic_scholar\', \'direct\'],
    ],

    \'export\' => [
        \'output_directory\' => storage_path(\'nexus/exports\'),
    ],

    \'queue\' => [
        \'name\' => env(\'NEXUS_QUEUE\', \'nexus\'),
    ],
];
```

---

## Jobs

### `SearchJob`

**File:** `src/Laravel/Jobs/SearchJob.php`

```php
final class SearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly SearchQuery $query,
        public readonly array       $providerAliases,
        public readonly string      $jobId,
        // pre-generated UUID passed from dispatch site
        public readonly ?string     $projectId = null,
    )

    public function handle(
        SearchAcrossProvidersHandler    $searchHandler,
        DeduplicateCorpusHandler        $dedupHandler,
        PersistSearchResultsListener    $persister,
    ): void
    {
        // 1. Dispatch SearchStarted event
        // 2. Run search
        // 3. Deduplicate results
        // 4. Persist: search_query, search_query_providers, documents, etc.
        // 5. Dispatch SearchCompleted event
    }

    public function failed(\\Throwable $e): void
    {
        // Dispatch SearchFailed event
        // Update search_queries.status = \'failed\'
    }
}
```

### `SnowballJob`

**File:** `src/Laravel/Jobs/SnowballJob.php`

```php
final class SnowballJob implements ShouldQueue
{
    public function __construct(
        public readonly CorpusSlice    $seedCorpus,
        public readonly SnowballConfig $config,
        public readonly array          $providerAliases,
        public readonly ?string        $projectId = null,
    )

    public function handle(RunSnowballHandler $handler): void
    // 1. Run snowball
    // 2. Persist new works discovered
    // 3. Dispatch SnowballCompleted event
}
```

### `DeduplicateCorpusJob`

**File:** `src/Laravel/Jobs/DeduplicateCorpusJob.php`

```php
final class DeduplicateCorpusJob implements ShouldQueue
{
    public function __construct(
        public readonly string  $projectId,
        public readonly array   $policyAliases = [],
    )

    public function handle(
        DeduplicateCorpusHandler $handler,
        SlrProject               $project,
    ): void
    // 1. Load works from DB for project
    // 2. Run deduplication
    // 3. Persist document_clusters and cluster_members
    // 4. Dispatch DeduplicationCompleted event
}
```

### `RetrieveFullTextJob`

**File:** `src/Laravel/Jobs/RetrieveFullTextJob.php`

```php
final class RetrieveFullTextJob implements ShouldQueue
{
    public function __construct(
        public readonly string $workId,     // persisted document ULID
        public readonly string $outputDir,
    )

    public function handle(RetrieveFullTextHandler $handler): void
    // 1. Load ScholarlyWork from DB
    // 2. Run fetch
    // 3. Persist pdf_fetches rows for each attempt
    // 4. Dispatch FullTextRetrieved or FullTextRetrievalFailed event
}
```

---

## Eloquent Models

### `ScholarlyWorkModel`

**File:** `src/Laravel/Models/ScholarlyWorkModel.php`

```php
/**
 * Eloquent model for the `documents` table.
 * This is NOT ScholarlyWork — it is an infrastructure projection.
 * Domain code must never import this class.
 */
class ScholarlyWorkModel extends Model
{
    protected $table = \'documents\';
    protected $keyType = \'string\';     // ULID
    public    $incrementing = false;

    protected $fillable = [
        \'id\', \'title\', \'year\', \'abstract\', \'venue\',
        \'url\', \'language\', \'cited_by_count\', \'is_retracted\', \'retrieved_at\',
    ];

    protected $casts = [
        \'is_retracted\' => \'boolean\',
        \'retrieved_at\' => \'datetime\',
    ];

    // Relationships
    public function externalIds(): HasOne       // document_external_ids
    public function authors(): BelongsToMany    // via document_authors
    public function providers(): HasMany        // document_providers
    public function queryLinks(): HasMany       // query_documents
    public function clusterMemberships(): HasMany  // cluster_members
    public function screeningDecisions(): HasMany
    public function pdfFetches(): HasMany

    // Domain mapping
    public function toDomain(): ScholarlyWork
    // Reconstructs ScholarlyWork value object from this model + relations
    // Requires: externalIds loaded, authors loaded
    public static function fromDomain(ScholarlyWork $work): self
    // Builds/fills model from domain object
}
```

### `SearchQueryModel`

**File:** `src/Laravel/Models/SearchQueryModel.php`

```php
class SearchQueryModel extends Model
{
    protected $table = \'search_queries\';
    protected $keyType = \'string\';
    public    $incrementing = false;

    protected $casts = [
        \'metadata\'    => \'array\',
        \'executed_at\' => \'datetime\',
    ];

    public function providers(): HasMany    // search_query_providers
    public function works(): BelongsToMany  // via query_documents
    public function project(): BelongsTo

    public static function fromDomain(SearchQuery $query, string $projectId): self
}
```

### `DedupClusterModel`

**File:** `src/Laravel/Models/DedupClusterModel.php`

```php
class DedupClusterModel extends Model
{
    protected $table = \'document_clusters\';
    protected $keyType = \'string\';
    public    $incrementing = false;

    protected $casts = [
        \'all_dois\'       => \'array\',
        \'all_arxiv_ids\'  => \'array\',
        \'provider_counts\'=> \'array\',
    ];

    public function representative(): BelongsTo  // → ScholarlyWorkModel
    public function members(): BelongsToMany     // via cluster_members → ScholarlyWorkModel
    public function project(): BelongsTo

    public static function fromDomain(DedupCluster $cluster, string $projectId): self
}
```

---

## Artisan Commands

### `NexusSearchCommand`

**File:** `src/Laravel/Commands/NexusSearchCommand.php`

```php
class NexusSearchCommand extends Command
{
    protected $signature = \'nexus:search
        {term : Search term}
        {--project= : Project ID}
        {--providers= : Comma-separated provider aliases}
        {--from= : Min publication year}
        {--to= : Max publication year}
        {--max=100 : Max results}
        {--export= : Output format (bibtex|ris|csv|json|jsonl)}
        {--output= : Output file path}
        {--async : Dispatch as queue job}\';

    protected $description = \'Search academic databases\';

    public function handle(): int
    // Builds SearchQuery, dispatches or runs synchronously
    // Prints summary table to stdout
    // If --export: runs export and prints file path
}
```

### `NexusSnowballCommand`

```
nexus:snowball
    {work-id  : WorkId to start from (e.g. doi:10.1234/abc)}
    {--project= : Project ID}
    {--depth=1 : Snowball depth (1-5)}
    {--forward : Forward snowballing}
    {--backward : Backward snowballing}
    {--async : Dispatch as queue job}
```

### `NexusDedupCommand`

```
nexus:dedup
    {project : Project ID to deduplicate}
    {--threshold=92 : Fuzzy match threshold (0-100)}
    {--policies= : Comma-separated policy names}
    {--dry-run : Show stats without persisting}
```

### `NexusFetchPdfCommand`

```
nexus:fetch-pdf
    {work-id : WorkId to fetch PDF for}
    {--output= : Output directory}
    {--all-pending : Fetch all pending works in project}
    {--project= : Project ID}
```

### `NexusExportCommand`

```
nexus:export
    {project : Project ID}
    {format : bibtex|ris|csv|json|jsonl|gexf|graphml|cytoscape}
    {--output= : Output file path}
    {--graph-id= : Citation graph ID for network exports}
    {--screened : Only export included/screened works}
```

---

## AI Tools (Laravel AI SDK)

### `LiteratureSearchTool`

**File:** `src/Laravel/Tools/LiteratureSearchTool.php`

```php
final class LiteratureSearchTool
{
    // Input schema:
    // {
    //   "term": string,
    //   "year_from": int|null,
    //   "year_to": int|null,
    //   "max_results": int = 100,
    //   "providers": string[] = []
    // }

    // Returns: JSON summary of CorpusSlice
    // {
    //   "total": int,
    //   "by_provider": {"openalex": 45, "arxiv": 22, ...},
    //   "top_works": [{title, doi, year, cited_by_count}, ...]  // top 5 by citation
    // }
}
```

### `SnowballTool`

```php
// Input: { "work_id": "doi:10.x", "depth": 1, "direction": "both|forward|backward" }
// Returns: { "new_works_found": int, "rounds": [{depth, new, known}] }
```

### `CitationGraphTool`

```php
// Input: { "project_id": "...", "type": "citation|co_citation|bibliographic_coupling" }
// Returns: { "nodes": int, "edges": int, "top_influential": [{work_id, title, pagerank}] }
```

---
'''

for name, content in docs.items():
    path = Path(name)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    print(f"wrote {name} ({len(content)} chars, {content.count(chr(10))} lines)")