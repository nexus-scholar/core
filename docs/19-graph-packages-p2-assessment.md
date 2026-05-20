# Graph Packages Assessment For P2 Citation Network

Last updated: 2026-05-20

This note records the scan of two graph packages now managed by the multi-repo workspace:

- `mbsoft31/graph-core` at `repos/graph-core`
- `mbsoft31/graph-algorithms` at `repos/graph-algorithms`

It answers whether they should be used for P2 citation-network implementation in `nexus-scholar/core`, and what needs package work first.

## Decision

Use both packages for P2, but keep them outside the `CitationNetwork\Domain` model.

`core` should own scholarly semantics:

- citation graph invariants
- references and citations from providers
- snowballing rounds
- co-citation builders
- bibliographic coupling builders
- persistence and audit

The graph packages should provide reusable infrastructure:

- graph representation
- generic algorithms
- graph export helpers
- performance-oriented adjacency handling

This keeps the Nexus domain independent while avoiding a new hand-rolled graph stack.

## Package Findings

### graph-core

Useful now:

- `Mbsoft\Graph\Domain\Graph` supports directed and undirected graphs.
- Public node identifiers are strings, which maps cleanly to `WorkId` values.
- Node and edge attributes can carry title, year, DOI, provider IDs, weights, graph type, and provenance.
- `successors()` and `predecessors()` match citation traversal needs.
- GraphML, GEXF, and Cytoscape exporters overlap with Nexus dissemination requirements.

Validation:

- `composer validate --strict` passes.
- `composer test` passes after installing with `--ignore-platform-req=php` on PHP 8.5.
- `composer analyse` passes.
- `composer format:check` is blocked by PHP CS Fixer not yet supporting PHP 8.5 syntax checks.

Maintenance needed:

- Refresh dev dependencies or lock constraints so `composer install` works without `--ignore-platform-req=php` on the active PHP version.
- Update PHPStan config away from deprecated `checkMissingIterableValueType`.
- Decide whether graph exporters should be consumed directly by `core` or remain a later replacement for existing serializers.

### graph-algorithms

Useful now:

- `PageRank` implements iterative PageRank with damping and convergence.
- `KCore` computes core numbers using an undirected view for directed graphs.
- `Dijkstra` supports shortest paths with a weight callback and non-negative weight validation.
- `StronglyConnected` supports directed component analysis.
- Centrality, traversal, pathfinding, decomposition, and link-prediction APIs are broadly aligned with P2 needs.

Validation:

- `composer validate --strict` fails because `composer.lock` is stale and missing dev dependency `laravel/pint`.
- `composer update --dry-run --ignore-platform-req=php` resolves a valid dependency set, but the lock file needs a real package-maintenance update before normal tests can run.

Maintenance needed:

- Refresh `composer.lock` in a dedicated branch.
- Run the full package suite after the lock refresh.
- Implement `Components\Connected`; it currently returns an empty array and contains a TODO.
- Add tests for `Connected` before using it for weak components in Nexus.
- Decide whether weighted PageRank is required. Existing PageRank is unweighted; this is acceptable for the first P2 slice, but weighted citation graphs may need a later algorithm option.

## Legacy Package Evidence

The legacy `nexus-php` package already used these graph packages:

- `CitationGraphBuilder` builds directed citation graphs and undirected co-citation / bibliographic coupling graphs with `Mbsoft\Graph\Domain\Graph`.
- `NetworkAnalyzer` runs `PageRank` and `StronglyConnected`.
- It also has local implementations for degree centrality, k-core graph extraction, BFS traversal, and citation paths.

Do not copy the old co-citation or bibliographic-coupling implementation as-is. It uses pairwise loops and `array_intersect`, while the current `core` docs require inverted-index builders.

## Recommended P2 Architecture

Add a graph adapter layer in `core`:

- `CitationNetwork\Infrastructure\Graph\MbsoftCitationGraphMapper`
  - Converts `CitationGraph` into `Mbsoft\Graph\Domain\Graph`.
  - Preserves node IDs, node attributes, edge weights, and graph type.
- `CitationNetwork\Infrastructure\Graph\MbsoftNetworkMetricsCalculator`
  - Wraps PageRank, degree centrality, K-core, and shortest paths.
  - Returns Nexus DTOs/value objects rather than graph-package arrays directly.
- `CitationNetwork\Application` handlers
  - Build graphs through domain services and ports.
  - Call graph algorithm ports through interfaces.
  - Persist graph snapshots and metadata through `CitationGraphRepositoryPort`.

Do not expose `Mbsoft\Graph\Domain\Graph` from the domain layer. It is an implementation detail.

## P2 Implementation Sequence

### P2.1.0 Package Readiness

Owner:

- `graph-algorithms`
- `graph-core`

Tasks:

1. Refresh `graph-algorithms/composer.lock`.
2. Run `composer validate --strict`, `composer test`, and `composer stan`.
3. Implement and test `Mbsoft\Graph\Algorithms\Components\Connected`.
4. Refresh `graph-core` dev dependencies so install works without ignoring PHP platform requirements.
5. Keep `graph-core` tests and static analysis green.

Done criteria:

- Both packages install normally in this workspace.
- Both packages have green test commands.
- Weak connected components are real, tested behavior.

### P2.1.1 Core Dependency And Adapter

Owner:

- `core`

Tasks:

1. Require `mbsoft31/graph-core` and `mbsoft31/graph-algorithms`.
2. Use local path repositories during multi-repo development if unpublished package versions are needed.
3. Add adapter tests proving a Nexus `CitationGraph` maps to an Mbsoft graph with:
   - directed/undirected mode
   - node IDs
   - node attributes
   - edge weights
   - duplicate edge handling
4. Add architecture tests ensuring graph packages are only imported from infrastructure/application adapters, not domain.

Done criteria:

- `core` can call graph algorithms through a port without leaking package types into domain APIs.

### P2.1.2 Citation Graph Builders

Owner:

- `core`

Tasks:

1. Strengthen `CitationGraph` invariants according to the current module docs.
2. Add a direct citation graph builder from works plus provider reference/citation IDs.
3. Add co-citation builder using inverted indexes:
   - group cited works per citing work
   - increment pair counts for cited works that appear together
   - write undirected weighted edges
4. Add bibliographic coupling builder using inverted indexes:
   - group references per work
   - invert reference ID to citing works
   - increment pair counts for works sharing references
   - write undirected weighted edges

Done criteria:

- No O(n^2) all-work pairwise scan for co-citation or bibliographic coupling.
- Weighted edges round-trip through persistence.

### P2.1.3 Metrics And Queries

Owner:

- `core`

Tasks:

1. Implement PageRank through `graph-algorithms`.
2. Implement in-degree, out-degree, and total degree metrics.
3. Implement K-core through `graph-algorithms`.
4. Implement shortest path through `Dijkstra`.
5. Store computed metrics in graph metadata or a future metrics table if JSON becomes too large.

Done criteria:

- Metrics can be recomputed deterministically from a persisted graph.
- Metrics are available to CLI, jobs, and future HTTP entrypoints through application services.

### P2.1.4 Snowballing

Owner:

- `core`

Tasks:

1. Define `SnowballingProviderPort` for references and citations.
2. Implement provider support only where APIs expose the needed data.
3. Track round depth, discovered count, already-known count, net-new count, and provider failures.
4. Deduplicate each round through existing dedup ports.
5. Persist progress and resulting graph changes.

Done criteria:

- Forward and backward snowballing are available without provider-specific code leaking into the domain.

## Test Strategy

Package tests:

- `graph-core`: graph mutation, attributes, exporters, static analysis.
- `graph-algorithms`: PageRank, K-core, Dijkstra, Connected, and algorithm edge cases.

Core unit tests:

- Citation graph invariants.
- Adapter mapping from `CitationGraph` to `Mbsoft\Graph\Domain\Graph`.
- Co-citation inverted-index builder.
- Bibliographic coupling inverted-index builder.
- Metrics calculator with fake and real graph adapters.

Core application tests:

- Build citation graph from provider references.
- Build similarity graphs and persist weighted edges.
- Compute metrics through a port.
- Provider failure does not corrupt a graph build.

Core feature tests:

- Migrations and repositories persist graph type, edge weights, and metadata.
- Laravel service provider binds graph algorithm ports.
- Commands/jobs later consume the same application services.

Performance guard:

- Add a representative graph test for co-citation and bibliographic coupling that would expose accidental all-work pairwise scans.

## Recommended Next Work

Start with `graph-algorithms` package readiness before implementing P2 in `core`.

The first concrete PR should be:

1. Refresh `graph-algorithms` lock file.
2. Implement `Components\Connected`.
3. Add tests for connected components on undirected graphs and weak components on directed graphs.
4. Run `composer validate --strict`, `composer test`, and `composer stan`.

After that, implement the `core` adapter and citation graph builder tests. This avoids starting P2 on top of an algorithm package with a known placeholder.
