# Bounded Contexts

## Overview

The package is split into four bounded contexts plus a shared kernel. The original repository had many modules under `src/`, including `Models`, `Core`, `Dedup`, `CitationAnalysis`, `Retrieval`, `Export`, `Visualization`, `Providers`, and `Laravel`. That structure is useful as a historical map, but the new version must make context boundaries explicit and semantic rather than purely technical. [cite:2][cite:5][cite:9][cite:10][cite:11][cite:14][cite:15][cite:16][cite:17]

## Shared Kernel

Namespace: `Nexus\Shared`

Shared Kernel owns concepts needed by every context:
- `WorkId`
- `WorkIdSet`
- `WorkIdNamespace`
- `Author`
- `AuthorList`
- `OrcidId`
- `Venue`
- base domain event interfaces

This is the only shared semantic surface allowed between contexts.

## Search Context

Namespace: `Nexus\Search`

Search owns:
- `SearchQuery`
- `ScholarlyWork` as seen in discovery/search
- `CorpusSlice`
- provider ports
- provider availability/rate limiting concerns
- search orchestration

Its main question is:
> “Which scholarly works match this search request across academic providers?”

## Deduplication Context

Namespace: `Nexus\Deduplication`

Deduplication owns:
- duplicate detection
- clustering
- representative election
- merge/fusion rules
- dedup provenance and confidence

Its main question is:
> “Which retrieved works are actually the same publication?”

The old code had conservative dedup logic implemented, but also advertised an aggressive strategy that did not exist. The new design must make the strategy portfolio explicit and either implement or omit each named policy honestly. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Citation Network Context

Namespace: `Nexus\CitationNetwork`

Citation Network owns:
- citation graphs
- snowballing
- co-citation and bibliographic coupling
- network metrics
- shortest paths
- graph persistence contracts

Its main question is:
> “How are these works related in the scholarly network?”

The old package supported citation analysis features well conceptually, but some graph builders used O(n²) pairwise comparisons and need an inverted-index based design from the start. [cite:4][Code Review](old-nexus-review/nexus-php-code-review.md)

## Dissemination Context

Namespace: `Nexus\Dissemination`

Dissemination owns:
- bibliography exports
- graph exports
- full-text retrieval
- file serialization
- storage abstraction

Its main question is:
> “How do we package, export, and retrieve artifacts from the corpus and its networks?”

## Laravel Integration Context

Namespace: `Nexus\Laravel`

This is not a domain bounded context. It is an integration shell.

It owns:
- service provider
- jobs
- events/listeners
- commands
- config publishing
- Eloquent persistence adapters
- AI tools and agents

The old package’s Laravel layer was one of its strengths because it did not deeply pollute the core. That separation must be preserved. [Code Review](old-nexus-review/nexus-php-code-review.md)[cite:17]

## Context Mapping Rules

- `Search` may emit a `CorpusSlice` that `Deduplication` consumes.
- `Deduplication` returns clusters and representatives that can be projected back into a deduplicated corpus.
- `CitationNetwork` consumes corpora and produces graphs/metrics.
- `Dissemination` consumes corpora and graphs to export formats or retrieve full text.
- `Laravel` consumes all application services but owns no core domain rules.

No context should directly depend on another context’s infrastructure implementation.