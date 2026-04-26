# Citation Network Module

## Purpose

This module turns a corpus into scholarly networks and supports:
- direct citation graphs
- co-citation graphs
- bibliographic coupling graphs
- influence metrics
- shortest path queries
- snowballing over references/citations

These capabilities were core advertised features of the old package and should remain central in the redesign. [cite:4][cite:9]

## Core Types

### CitationGraph
Aggregate root containing:
- nodes = scholarly works
- edges = citation links
- graph type
- optional metrics snapshot

Invariant:
- an edge cannot be recorded unless the relevant nodes exist in the graph

### CitationLink
Directed edge from citing work to cited work.

### SnowballConfig
Controls:
- forward snowballing
- backward snowballing
- recursion depth
- citation/reference fetch limits

The old `SnowballService` supported forward/backward and recursive depth, but it also hardcoded a conservative dedup strategy in its constructor. The redesign separates snowballing orchestration from dedup policy configuration. [cite:8][Code Review](old-nexus-review/nexus-php-code-review.md)

### SnowballRound
Represents one expansion step:
- total discovered works
- already-known works
- net new works
- depth

This is a better domain concept than returning a raw array.

### InfluentialWork
Represents a work with computed importance metrics like PageRank and degree.

## Graph Construction

### Citation graph
Straightforward directed graph:
- node per representative work
- edge from work A to work B if A cites B

### Co-citation graph
Two works are connected if they are cited together by later works.

### Bibliographic coupling graph
Two works are connected if they share references.

## Performance Rule

Do **not** implement co-citation or bibliographic coupling with naive O(n²) pairwise comparisons across all works. The old review identified this as a serious scaling issue. Build inverted indexes instead:
- for co-citation: citing work -> cited works, then count co-occurrence pairs
- for bibliographic coupling: work -> references, then invert through shared references [Code Review](old-nexus-review/nexus-php-code-review.md)

## Metrics

Support at least:
- PageRank
- degree centrality
- in-degree/out-degree
- k-core decomposition
- shortest paths

The original package already advertised PageRank, centrality, k-core, and shortest citation paths, so these remain part of the target feature set. [cite:4]

## Snowballing

Snowballing is recursive graph-informed retrieval.

Rules:
- forward means fetch works that cite a seed
- backward means fetch works referenced by a seed
- deduplicate each round before adding to the corpus
- round result includes only new works not already known
- provider support may vary; not every provider must support both directions equally

## Persistence

The database design proposed:
- `citation_graphs`
- `citation_edges`

with graph type, node count, edge count, and metadata JSON for metrics. This is a good persistence projection because graphs are expensive enough that rebuilding them on every page load is wasteful. [Schema Review](old-nexus-review/nexus-php-database-schema.md)