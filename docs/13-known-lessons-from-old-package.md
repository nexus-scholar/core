# Known Lessons from the Old Package

## Why this file exists

This file documents concrete lessons from the deep review of the previous implementation so the new agent does not accidentally rebuild the same mistakes.

## Lesson 1: A utility that is not wired is not a feature
The old package had a rate limiter implementation, but no provider actually called it. Result: configured limits were fiction. In the redesign, rate limiting must be structurally unavoidable in provider request paths. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Lesson 2: Documentation must not promise missing strategies
The old package advertised an aggressive deduplication strategy that did not exist. The redesign must never document a policy or module before it exists, or must clearly mark it as planned. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Lesson 3: Shared mutable singletons are dangerous
The old Laravel search flow mutated a singleton provider registry per request, which is unsafe under concurrency. The redesign must construct providers once and treat per-request selection immutably. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Lesson 4: Cache identity must include all semantics
The old search cache key omitted dimensions that affected results. The redesign makes `SearchQuery` own cache identity. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Lesson 5: Bundled CA files rot
The old repository committed a `cacert.pem` file. The redesign must rely on maintained system/composer CA discovery. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Lesson 6: Hidden hardcoding breaks configurability
The old snowball service hardcoded dedup behavior and the old fusion logic ignored configured provider priority. The redesign must keep strategy/config choices injectable. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Lesson 7: O(n²) graph logic becomes a product bug
Co-citation and bibliographic coupling cannot be naive pairwise comparisons for realistic corpora. Scalability is a domain requirement, not an optimization afterthought. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Lesson 8: Raw payload retention is expensive
Storing raw provider payloads on every work is a memory trap. Raw snapshots must be explicit and off by default. [Code Review](old-nexus-review/nexus-php-code-review.md) [Schema Review](old-nexus-review/nexus-php-database-schema.md)

## Lesson 9: Multilingual text needs Unicode-safe matching
Byte-based string distance is not adequate for international titles. Dedup must be Unicode-aware. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Lesson 10: Persistence should preserve decisions, not just facts
The schema review correctly emphasized immutable findings and mutable review decisions. This philosophy should shape not just the DB schema but also the application service design. [Schema Review](old-nexus-review/nexus-php-database-schema.md)