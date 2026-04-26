# Shared Kernel

## Mission

The Shared Kernel contains only concepts truly shared by all contexts. Keep it small and stable.

## WorkIdNamespace

This enum defines supported identifier namespaces:
- DOI
- ARXIV
- OPENALEX
- S2
- PUBMED
- IEEE
- DOAJ

It exists because the original package supported multiple academic providers and multiple external ID types through a flat `ExternalIds` structure. The redesign replaces that nullable bag with typed identity objects. [cite:4][cite:7]

## WorkId

`WorkId` is the canonical typed identifier in the system.

Responsibilities:
- hold namespace
- hold normalized value
- compare equality
- serialize to string
- construct from string if needed

Normalization rules:
- DOI strips `https://doi.org/`, `http://dx.doi.org/`, and `doi:` prefixes
- DOI lowercases final value
- Other IDs are trimmed and lowercased where appropriate

This centralizes the DOI normalization logic that was duplicated in the old package. [cite:7][Code Review](old-nexus-review/nexus-php-code-review.md)

## WorkIdSet

`WorkIdSet` replaces old-style nullable `ExternalIds`.

Responsibilities:
- hold 0..n known `WorkId`s
- return primary ID according to precedence
- check overlap with another set
- find by namespace
- remain immutable

Primary ID precedence:
1. DOI
2. OpenAlex
3. Semantic Scholar
4. arXiv
5. PubMed
6. IEEE
7. DOAJ

Rationale:
- DOI is globally strongest when available
- provider-native graph-centric IDs such as OpenAlex and S2 are often more operationally useful than arXiv IDs for graph building and enrichment

## Author and AuthorList

The original package had an `Author` model and the database review proposed normalized authors plus a join table with authorship position. The new design keeps `Author` as a shared value object and lets persistence adapt it into normalized relational tables later. [cite:5][Schema Review](old-nexus-review/nexus-php-database-schema.md)

`Author` fields:
- family name
- given name
- ORCID
- normalized display name if needed

`AuthorList` exists so authors are not represented as raw arrays throughout the domain.

## Venue

A lightweight shared value object representing publication venue:
- name
- type
- optional ISSN/other metadata

## DomainEvent

A minimal shared interface for domain events.

Events are important because the old package’s domain logic had almost no event surface, which limited decoupled reactions such as persistence, analytics, and async follow-up jobs. [Code Review](old-nexus-review/nexus-php-code-review.md)