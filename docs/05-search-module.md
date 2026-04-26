# Search Module

## Purpose

The Search module discovers scholarly works across academic providers and returns a bounded corpus.

The original package exposed multi-provider search across OpenAlex, Crossref, arXiv, Semantic Scholar, PubMed, DOAJ, and IEEE Xplore. That breadth is valuable and must be preserved. [cite:4][cite:11]

## Domain Concepts

### SearchQuery
A structured request with:
- term/text
- year range
- language
- max results
- offset
- optional metadata/filters
- includeRawData flag
- stable ID
- authoritative cache key logic

The old package had a `Query` model but the cache key omitted important dimensions, so the redesign makes `SearchQuery` responsible for its own cache identity. [Code Review](old-nexus-review/nexus-php-code-review.md)

### ScholarlyWork
In the Search context, `ScholarlyWork` represents a discovered work and may include:
- IDs
- title
- authors
- abstract
- venue
- publication year
- language
- cited-by count
- retraction status
- source provider
- retrieval timestamp
- raw data only if explicitly requested

The old package’s `Document` constructor included fields such as `provider`, `providerId`, `externalIds`, `abstract`, `authors`, `venue`, `url`, `language`, `citedByCount`, `queryId`, `queryText`, `retrievedAt`, `clusterId`, and `rawData`. Some of those belong to other contexts or persistence concerns, so the redesign strips cross-context leakage from the domain model. [cite:6]

### CorpusSlice
An aggregate representing the set of works resulting from one search step.

## Ports

### AcademicProviderPort
Every provider adapter must implement:
- `alias()`
- `search(SearchQuery): ScholarlyWork[]`
- `fetchById(WorkId): ?ScholarlyWork`
- `supports(WorkIdNamespace): bool`

### RateLimiterPort
Every provider request path must use this before hitting the network. The old package defined a rate limiter but never wired it into providers, which is one of the most important lessons from the review. [Code Review](old-nexus-review/nexus-php-code-review.md)

### HttpClientPort
A wrapper around actual HTTP transport. This keeps provider code independent of Guzzle or any specific client.

### SearchCachePort
Abstracts result caching. It must support retrieval, put, and invalidation by version/prefix rather than relying on silently unused tag behavior like the old code. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Provider Adapter Rules

All provider adapters must:
- respect rate limits
- respect timeouts
- normalize IDs via `WorkId`
- map raw responses to `ScholarlyWork`
- never return partial invalid domain entities
- surface provider failures as meaningful exceptions or result envelopes
- be testable through recorded fixtures

## Parallelization

The search use case should be designed so providers may be called concurrently where appropriate. The original package conceptually fanned out across providers, but parallel orchestration should be explicit in the redesign to reduce latency.

## Caching Rules

Cache keys must include:
- query text
- year range
- language
- max results
- offset
- provider selection
- metadata affecting results

This directly avoids the old stale-result bug. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Testing Guidance

Test the Search module in layers:

- value-object tests for query rules
- mocked provider tests for orchestration
- VCR-backed integration tests for each adapter
- Laravel feature tests only in the Laravel layer

Never use real provider HTTP in CI.