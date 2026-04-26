# Persistence Model

## Philosophy

Persistence follows three core principles:

1. Immutable scholarly findings, mutable decisions.
2. Cluster-aware dedup provenance.
3. Query provenance for reproducible reviews and PRISMA reporting. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

The earlier schema design used `Document` terminology because it mirrored the original package. In the redesign, domain code uses `ScholarlyWork`, but relational tables may still use names like `documents` if that eases compatibility, or be renamed to `scholarly_works` if starting fresh. The important thing is conceptual consistency.

## Core Tables

### Projects
A project scopes all queries, corpora, clusters, screening decisions, and graphs. Without this, multiple literature reviews are impossible to isolate cleanly. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### Search Queries
Persist full query inputs, execution status, totals, duration, and timing.

### Search Query Providers
Persist per-provider execution outcome for provenance and progress tracking. This mirrors the earlier schema idea and is crucial for PRISMA-style reporting. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### Works / Documents
Persist representative scholarly works:
- title
- abstract
- year
- venue
- URL
- language
- cited-by count
- retraction status
- retrieved timestamp

Important decisions:
- no provider column on the central work table
- no rawData column by default [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### External IDs
Persist identifiers separately and index them heavily. The proposed schema’s unique DOI constraint is important because it gives the database a last-line defense against accidental duplicate persistence. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### Provider Sightings
Keep a join/provenance table for provider-specific IDs and sightings.

### Authors
Normalize authors into their own table plus a positional join table. This was one of the strong recommendations of the schema review because it supports cross-project author queries and better dedup behaviors. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### Query-to-Work Provenance
A join table linking each query, work, provider, and provider ID. This is essential for traceability. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### Clusters and Cluster Members
Persist dedup outcomes with strategy and thresholds so reruns are comparable and auditable. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### Screening Decisions
Persist multi-stage screening decisions instead of overwriting a single status. This provides audit history and conflict handling. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### PDF Fetches
Persist per-source attempts, not just the final success. This is useful operationally and analytically. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

### Citation Graphs and Edges
Persist graph snapshots and metrics so repeated visualization or analysis doesn’t require full rebuilds. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

## Migration Ordering

Respect dependency ordering between tables. The earlier schema review already laid out a sane order and it should be followed to reduce migration churn:
1. projects
2. authors
3. works/documents
4. external IDs
5. provider sightings
6. work-authors
7. search queries
8. query provider progress
9. query-to-work
10. clusters
11. cluster members
12. screening decisions
13. PDF fetches
14. citation graphs
15. citation edges [Schema Review](old-nexus-review/nexus-php-database-schema.md)