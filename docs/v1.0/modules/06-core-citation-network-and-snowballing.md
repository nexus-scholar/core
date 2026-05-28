# Core Citation Network And Snowballing

## Purpose

The Citation Network context builds graph views over a corpus and expands a corpus through citation traversal. It owns citation graph domain objects, graph construction, network metrics, shortest paths, citation graph persistence, graph serialization support, and snowballing provider orchestration.

This context turns a locked or working corpus into graph structures that can support analysis and export.

## v1 Status

Status: shipped in v1.0.0.

The v1 package includes:

- direct citation graphs,
- co-citation graphs,
- bibliographic-coupling graphs,
- citation links and paths,
- network metrics,
- graph persistence,
- graph algorithm adapter ports,
- shortest path handling,
- graph export support through the Dissemination context,
- forward and backward snowballing,
- provider selection for snowballing,
- deduplication of snowball results,
- lock-policy enforcement for corpus growth.

## What Shipped

### Graph Domain

The graph domain includes:

- `CitationGraphType`,
- `CitationGraphId`,
- `CitationLink`,
- `CitationGraph`,
- `CitationPath`,
- `NetworkMetrics`,
- `SnowballDirection`.

`CitationGraph` is project-scoped. It can store works and citation links, and direct graph edges are idempotent. The domain model can represent citation links independently of the graph-builder implementation.

### Graph Builders

`CitationGraphBuilder` builds three graph types:

- `citation`,
- `co_citation`,
- `bibliographic_coupling`.

Direct citation graphs are built from work reference identifiers. The builder records direct edges when both sides are present in the indexed corpus. Co-citation and bibliographic-coupling graphs are built through grouped identifier indexes instead of naive all-work pair scanning.

### Analysis And Paths

`AnalyzeNetworkHandler` loads a persisted graph, delegates metric computation to `GraphAlgorithmPort`, and stores metrics by default.

`FindShortestCitationPathHandler` loads a graph and delegates shortest-path computation to the same graph algorithm abstraction.

The graph implementation uses adapter boundaries so the domain does not expose third-party graph-library internals.

### Snowballing

`SnowballCorpusHandler` expands a corpus from seed works. It:

- verifies the corpus can run snowballing,
- selects providers by alias,
- supports forward and backward traversal,
- tracks depth,
- asks providers for citing or referenced works,
- records provider stats and failures,
- deduplicates each round,
- subtracts works already known to the corpus,
- uses only net-new works as the next depth seeds.

Snowballing providers include the providers that implement `SnowballingProviderPort`. OpenAlex and Semantic Scholar support both directions. Crossref supports reference traversal where available.

## Public API / Commands

The main application entry points are:

- `BuildCitationGraphHandler`
- `AnalyzeNetworkHandler`
- `FindShortestCitationPathHandler`
- `SnowballCorpusHandler`

The main ports are:

- `CitationGraphRepositoryPort`
- `GraphAlgorithmPort`
- `SnowballingProviderPort`
- `DeduplicationPort`
- `ProjectWorkMembershipPort`
- `CorpusLockPolicy`

`SnowballingProviderCollection` selects provider adapters and rejects unknown provider aliases.

## Data Model And Persistence

Citation graph persistence uses:

- `citation_graphs`,
- `citation_edges`.

Graph records store graph identity, project id, graph type, metadata, and computed metrics. Edge records store directed or weighted graph relationships depending on graph type.

Snowballing itself returns application results and contributes works through downstream corpus workflows; it is not a separate persisted graph entity.

## Main Workflows

### Build A Graph

1. Caller provides graph type, project id, and corpus.
2. Handler optionally verifies locked membership when a lock policy is available.
3. Builder creates the requested graph type.
4. Handler persists the graph when requested.
5. Graph id and graph content are returned.

### Analyze A Graph

1. Handler loads a graph by id.
2. Graph algorithm adapter computes network metrics.
3. Metrics are stored unless disabled by command options.
4. Metrics are returned to the caller.

### Find A Shortest Citation Path

1. Handler loads a graph by id.
2. Graph algorithm adapter computes the path between source and target ids.
3. A `CitationPath` result is returned when a path exists.

### Snowball A Corpus

1. Handler checks that the project corpus can be mutated by snowballing.
2. Seed works are sent to selected providers.
3. Forward and backward results are collected per depth.
4. Provider failures are recorded without discarding successful provider results.
5. New works are deduplicated against the known corpus.
6. Net-new works become the next depth seeds and are returned in the result.

## Validation And Tests

Citation network behavior is covered by:

- graph domain tests,
- direct citation graph builder tests,
- co-citation builder tests,
- bibliographic-coupling builder tests,
- build handler persistence tests,
- locked membership tests,
- network analysis tests,
- shortest path tests,
- snowballing tests for directions, depth, provider failures, unknown aliases, and lock behavior,
- graph serializer tests through Dissemination,
- citation graph repository feature tests,
- queued snowball job tests.

Relevant test paths:

- `tests/Unit/CitationNetwork`
- `tests/Feature/Persistence/CitationGraphRepositoryTest.php`
- `tests/Feature/Laravel/SnowballJobTest.php`
- `tests/Unit/Dissemination/Infrastructure/Serializer`

## What Did Not Ship In v1

The Citation Network context does not ship:

- browser visualization,
- a persisted snowball round ledger,
- guaranteed snowballing support for every search provider,
- automatic corpus rebuild policy after snowballing,
- graph algorithm internals as part of the public package contract.

Hosts can build product workflows around the handlers, but the stable package contract is the domain, handlers, ports, repository, and serializers.

## Changed From Earlier Specs

- Earlier docs treated network analysis, graph algorithms, and snowballing as roadmap items; these are implemented in v1.
- Graphs are project-scoped.
- Co-citation and bibliographic-coupling builders use grouped indexes.
- Direct graph building only records direct edges for references that resolve inside the indexed corpus.
- Network metrics can be persisted through the citation graph repository.
- Snowballing uses provider ports and result DTOs rather than a separate round aggregate.

## Implementation References

- Code references:
  - `src/CitationNetwork`
  - `src/Search/Infrastructure/Provider`
  - `src/Dissemination/Infrastructure/Serializer`
  - `src/Laravel/Persistence/Repository/EloquentCitationGraphRepository.php`
  - `tests/Unit/CitationNetwork`
  - `tests/Feature/Persistence/CitationGraphRepositoryTest.php`
  - `tests/Feature/Laravel/SnowballJobTest.php`