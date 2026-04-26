# TDD Strategy

## Principle

All development proceeds test-first. The purpose is not just regression prevention. The purpose is to force the language and boundaries to emerge from behavior.

The original package already had an extensive test suite, which is a positive sign worth preserving as a discipline. [cite:4][Code Review](old-nexus-review/nexus-php-code-review.md)

## Layer 1: Value Objects

Write these first:
- `WorkId` normalization and equality
- `WorkIdSet` overlap and primary selection
- `SearchQuery` validation and cache key behavior
- year range validation
- language code validation
- author list helper behavior

These are fast and define the semantics of the language.

## Layer 2: Entities and Aggregates

Write tests for:
- `ScholarlyWork` identity equivalence through shared IDs
- `CorpusSlice` add/contains/merge behavior
- `DedupCluster` representative/member logic
- `CitationGraph` invariants, especially rejecting dangling edges
- `SnowballRound` counting new vs already-known works

## Layer 3: Application Services

Mock ports and test use cases:
- search across providers
- deduplicate corpus
- run snowball
- export bibliography
- retrieve full text

The test should read like a specification, not like framework plumbing.

## Layer 4: Adapter Integration Tests

Use recorded HTTP fixtures:
- OpenAlex adapter mapping
- Crossref adapter mapping
- arXiv adapter XML parsing
- Semantic Scholar adapter mapping

Do not call live APIs in CI.

## Layer 5: Laravel Feature Tests

Only here do you test:
- service provider bindings
- job dispatches
- DB persistence projections
- command execution
- published config

## Test Naming Style

Use behavior-focused names:
- `it_normalizes_doi_prefixes`
- `it_rejects_inverted_year_ranges`
- `it_clusters_two_works_with_the_same_doi`
- `it_refuses_to_record_a_citation_for_a_missing_node`
- `it_only_returns_new_works_in_a_snowball_round`

Avoid generic names like:
- `testQuery`
- `testProvider`
- `it_works`

## Golden Rule

If a test requires a real network, a real DB, and a queue worker just to validate a domain rule, the design is wrong.