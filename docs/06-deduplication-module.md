# Deduplication Module

## Purpose

Deduplication identifies multiple provider sightings of the same publication and groups them into explicit clusters with one representative.

The old package already used Union-Find, which is one of the strongest ideas worth preserving. That pattern is algorithmically sound for clustering duplicate relations in the typical SLR corpus size. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Domain Concepts

### Duplicate
A value object saying:
- work A
- work B
- reason
- confidence

Reasons may include:
- DOI match
- arXiv match
- OpenAlex match
- Semantic Scholar match
- fuzzy title match
- fingerprint match

### DedupCluster
An aggregate with:
- cluster ID
- representative work
- member works
- duplicate evidence
- provenance/settings used to form it

### Representative
The canonical work chosen to stand for the cluster.

## Why a separate module

The old package leaked dedup concerns into the `Document` model through fields like `clusterId`, and some dedup settings were hardcoded or ignored. The redesign keeps dedup logic in its own bounded context and treats cluster membership as dedup-owned state, not search-owned state. [cite:6][Code Review](old-nexus-review/nexus-php-code-review.md)

## Policies

The module supports a pipeline of policies.

### Exact ID policies
Run first because they are cheap and highly reliable:
- DOI exact match
- arXiv exact match
- OpenAlex exact match
- S2 exact match
- PubMed exact match where useful

### Fuzzy title policy
Runs after exact-ID policies and only on unresolved candidates.

Unicode safety matters. The old package used byte-based `strlen()` and `levenshtein()`, which is unreliable for multilingual titles. The redesign must use Unicode-aware normalization and comparison or controlled ASCII-normalization before edit distance. [Code Review](old-nexus-review/nexus-php-code-review.md)

### Fingerprint policy
A derived matching policy using normalized title fragments + authors + year window.

## Representative Election

Representative selection must be explicit and configurable.

Suggested default ranking:
1. richer IDs
2. DOI presence
3. abstract presence
4. venue presence
5. higher provider quality score
6. longer title/metadata completeness
7. source priority from config

The old package had a hardcoded provider priority array inside fusion logic and ignored configured provider priority. The redesign must treat priority as a first-class injected policy or config surface. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Cluster Persistence

The database review proposed:
- `document_clusters`
- `cluster_members`

with stored strategy, thresholds, size, and representative flags. This is excellent because it preserves dedup provenance, supports PRISMA-style reproducibility, and allows re-running dedup without destroying original findings. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

## Truth Model

Important rule:
- original sightings are immutable observations
- dedup clusters are decisions about those observations

This mirrors the database design principle “immutable documents, mutable decisions,” which is the right mental model even if the domain term becomes `ScholarlyWork` rather than `Document`. [Schema Review](old-nexus-review/nexus-php-database-schema.md)