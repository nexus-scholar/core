# Class Specs — Shared Kernel

> **File:** `docs/spec-shared-kernel.md`
> **Namespace root:** `Nexus\Shared`
> **Rule:** No `Illuminate\*`. No provider logic. No framework imports.

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
    // valid format: \d{4}-\d{4}-\d{4}-\d{3}[\dX]

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
    public function occurredAt(): \DateTimeImmutable;
    public function eventName(): string;   // e.g. "search.query.executed"
}
```

---

## `DomainException` (base)

**File:** `src/Shared/DomainException.php`

```php
abstract class DomainException extends \RuntimeException {}
```

All context-specific exceptions extend this.

---
