# Product Vision

## Purpose

`nexus-php` is a PHP library for systematic literature reviews (SLR). It searches multiple academic databases, deduplicates overlapping results, expands a corpus by forward/backward snowballing, builds citation-based networks, retrieves full-text PDFs, exports bibliographic records, and integrates cleanly with Laravel without contaminating the core domain. [cite:4][Code Review](old-nexus-review/nexus-php-code-review.md)

The new implementation is **not** a patch of the old package. It is a fresh design informed by a deep review of the original codebase, its architecture, and its persistence needs. The original package has a strong layered direction and good separation between Laravel and core logic, but it also contains several correctness and architectural problems such as an unwired rate limiter, unsafe singleton mutation in Laravel search flows, incomplete cache key construction, stale CA bundle management, hardcoded dedup choices, and O(n²) graph building paths that do not scale well. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Product Goals

The package must provide these capabilities as first-class use cases:

- Search OpenAlex, Crossref, arXiv, Semantic Scholar, PubMed, DOAJ, and IEEE Xplore through a consistent provider model. [cite:4][cite:11]
- Deduplicate works from multiple sources into stable clusters with one chosen representative. [cite:4][cite:10][Schema Review](old-nexus-review/nexus-php-database-schema.md)
- Support snowballing by discovering citing and referenced works from seed works. [cite:4][cite:8]
- Build citation, co-citation, and bibliographic coupling graphs, then run network metrics such as PageRank and k-core. [cite:4][cite:9]
- Export bibliographic records in BibTeX, RIS, CSV, JSON, and JSONL, and export graphs in GEXF, GraphML, and Cytoscape formats. [cite:4][cite:15][cite:16]
- Retrieve PDFs via multiple full-text sources and persist fetch attempts. [cite:4][cite:14][Schema Review](old-nexus-review/nexus-php-database-schema.md)
- Support Laravel jobs, commands, tools, and AI-agent integrations as a thin adapter layer only. [cite:4][cite:17]

## Non-Goals

This package does **not** own user accounts, team management, generic auth, or host-app policies. Those belong to the application that installs the package. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

This package does **not** store raw provider payloads by default. Raw data is memory-heavy and was explicitly identified as a risk in the old implementation, so raw snapshots are opt-in only. [Code Review](old-nexus-review/nexus-php-code-review.md)[Schema Review](old-nexus-review/nexus-php-database-schema.md)

## Quality Goals

The redesign must optimize for:

- Correctness first.
- Explicit domain language.
- Deterministic testability.
- Memory safety.
- Provider rate-limit safety.
- Reproducible research provenance.
- Clear module boundaries.
- Laravel optionality. [Code Review](old-nexus-review/nexus-php-code-review.md)[Schema Review](old-nexus-review/nexus-php-database-schema.md)