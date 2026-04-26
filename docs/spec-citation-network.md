# Class Specs — Citation Network Module

> **File:** `docs/spec-citation-network.md`
> **Namespace:** `Nexus\CitationNetwork`
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
ArXiv will NOT implement this — it doesn't provide citation data.

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
