# Class Specs — Search Domain

> **File:** `docs/spec-search-domain.md`
> **Namespace:** `Nexus\Search\Domain`
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
        private \DateTimeImmutable $retrievedAt,
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
    public function retrievedAt(): \DateTimeImmutable
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
final class InvalidSearchTerm   extends \Nexus\Shared\DomainException {}
final class InvalidYearRange    extends \Nexus\Shared\DomainException {}
final class ProviderUnavailable extends \Nexus\Shared\DomainException {
    public function __construct(
        public readonly string $providerAlias,
        string $reason,
        ?\Throwable $previous = null,
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
