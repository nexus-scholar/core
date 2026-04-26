# Class Specs — Deduplication Module

> **File:** `docs/spec-deduplication.md`
> **Namespace:** `Nexus\Deduplication`
> **Rule:** No framework. No HTTP. Depends on `Nexus\Search\Domain` for `ScholarlyWork`.

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
