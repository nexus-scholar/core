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

## Current Status

Status on 2026-05-20:

- Package readiness work has been completed in the local workspace.
- `graph-core` was upgraded for the active PHP toolchain.
- `graph-algorithms` now implements real connected-components behavior.
- `core` requires both graph packages through local path repositories while developing the multi-repo workspace.
- `core` now includes the graph adapter, metrics calculator, citation graph builders, application handlers, Laravel bindings, and tests for the first P2 citation-network slice.

The remaining graph-specific P2 work is progress persistence and graph rebuild policy, not graph package readiness.

## Package Findings

### graph-core

Useful now:

- `Mbsoft\Graph\Domain\Graph` supports directed and undirected graphs.
- Public node identifiers are strings, which maps cleanly to `WorkId` values.
- Node and edge attributes can carry title, year, DOI, provider IDs, weights, graph type, and provenance.
- `successors()` and `predecessors()` match citation traversal needs.
- GraphML, GEXF, and Cytoscape exporters overlap with Nexus dissemination requirements.

Validation to run before release:

- `composer validate --strict`
- `composer test`
- `composer analyse`
- `composer format:check`

Maintenance completed:

- Dev tooling was refreshed for current PHP support.
- Static-analysis configuration was updated as part of package maintenance.

Still to decide:

- Whether graph exporters should be consumed directly by `core` or remain a later replacement for existing serializers.

### graph-algorithms

Useful now:

- `PageRank` implements iterative PageRank with damping and convergence.
- `KCore` computes core numbers using an undirected view for directed graphs.
- `Dijkstra` supports shortest paths with a weight callback and non-negative weight validation.
- `StronglyConnected` supports directed component analysis.
- Centrality, traversal, pathfinding, decomposition, and link-prediction APIs are broadly aligned with P2 needs.

Maintenance completed:

- `composer.lock` was refreshed.
- `Components\Connected` is implemented.
- Connected-component tests cover undirected graphs and weak components for directed graphs.

Validation to run before release:

- `composer validate --strict`
- `composer test`
- `composer stan`

Still to decide:

- Whether weighted PageRank is required. Existing PageRank is unweighted; this is acceptable for the first P2 slice, but weighted citation graphs may need a later algorithm option.

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

Status: done

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
- Both packages have green test commands before release work.
- Weak connected components are real, tested behavior.

### P2.1.1 Core Dependency And Adapter

Status: done

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

Status: done for in-memory/provider-ID inputs, pending live provider traversal

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

Status: done for current metrics and shortest-path use cases

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

Status: partially implemented

Owner:

- `core`

Tasks:

1. `SnowballingProviderPort` for references and citations is implemented.
2. `SnowballCorpusHandler` runs selected providers for forward/backward expansion.
3. Round depth, discovered count, already-known count, net-new count, and provider failures are tracked in result DTOs.
4. Discovered works are deduplicated through existing dedup ports.
5. Semantic Scholar provider support is implemented for citation/reference traversal.
6. OpenAlex provider support is implemented for forward citation traversal and backward `referenced_works` traversal.
7. Crossref provider support is implemented for deposited DOI references; forward citation traversal is intentionally unsupported in the public metadata adapter.
8. `SnowballJob` wraps `SnowballCorpusHandler` for queue-safe Laravel execution with lifecycle events.
9. Persisted progress and resulting graph changes are still pending.

Done criteria:

- Forward and backward snowballing are available through supported providers without provider-specific code leaking into the domain.
- Persisted progress is available for host-app status screens.

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

Do not start with graph package readiness anymore; that part is complete for the local workspace.

The next graph-specific PR should be:

1. Add provider-progress events for snowballing rounds.
2. Persist or emit provider-level progress without duplicating handler work.
3. Keep snowball progress state queryable for host-app status screens.
4. Add more provider adapters only with fixtures and only where APIs expose reliable citation/reference data.
5. Persist round progress after the job/progress event shape is stable.

Run the graph package test suites again before tagging package releases, but the next Nexus feature work can proceed from the `core` citation-network application layer.
