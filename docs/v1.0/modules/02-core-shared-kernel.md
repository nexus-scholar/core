# Core Shared Kernel

## Purpose

The shared kernel contains the reusable concepts that every core bounded context depends on. It provides identity, authorship, publication metadata, corpus slices, lock-state primitives, job lifecycle values, cross-context ports, and a small lock policy service.

The design goal is to keep these concepts stable and framework-light. Provider logic, persistence details, HTTP behavior, queue behavior, and product workflow rules belong outside the shared kernel.

## v1 Status

Status: shipped in v1.0.0.

The shipped shared kernel is broader than the earliest shared-kernel spec. The first design focused mainly on work identity, authors, venues, and domain events. v1 also includes:

- canonical scholarly work and corpus slice domain objects,
- project lock and corpus snapshot value objects,
- job lifecycle value objects and reader/recorder ports,
- shared membership, lock, snapshot, and transaction ports,
- `CorpusLockPolicy` as a cross-context application service.

The live source of truth is `src/Shared` and `tests/Unit/Shared`.

## What Shipped

### Namespace Shape

The shipped namespace root is `Nexus\Shared` under PSR-4 autoload root `Nexus\`.

| Namespace | Purpose |
| --- | --- |
| `Nexus\Shared\Domain` | Canonical scholarly work and corpus slice domain objects. |
| `Nexus\Shared\ValueObject` | Identifier, author, venue, language, lock, snapshot, operation, and job lifecycle values. |
| `Nexus\Shared\Application` | Cross-context application policy for corpus locking and export metadata. |
| `Nexus\Shared\Port` | Framework-facing ports for locks, membership, snapshots, job lifecycle, project corpus works, and transactions. |
| `Nexus\Shared\Contract` | Minimal domain event and base domain exception contracts. |
| `Nexus\Shared\Exception` | Shared project/corpus lock exceptions. |

### Work Identity

`WorkIdNamespace` is the domain enum for supported scholarly work identifier namespaces. v1 ships nine namespaces:

- `doi`
- `arxiv`
- `openalex`
- `s2`
- `pubmed`
- `pmcid`
- `ieee`
- `doaj`
- `internal`

`WorkId` stores one namespace/value pair in normalized form. DOI values strip `https://doi.org/`, `http://dx.doi.org/`, and `doi:` prefixes before lowercasing. arXiv values strip an `arxiv:` prefix before lowercasing. Other namespaces are trimmed and lowercased.

`WorkId::toString()` serializes as `<namespace>:<value>`. `WorkId::fromString()` rejects strings without a namespace separator, empty values, or unknown namespaces.

`WorkIdSet` is an immutable collection of work identifiers. It supports:

- `empty()`
- `fromArray()`
- `add()`
- `findByNamespace()`
- `primary()`
- `hasOverlapWith()`
- `isEmpty()`
- `count()`
- `all()`
- `merge()`
- `toString()`

Primary identifier precedence in v1 is:

1. DOI
2. OpenAlex
3. Semantic Scholar
4. arXiv
5. PubMed Central
6. PubMed
7. IEEE
8. DOAJ
9. Internal

`WorkIdSet::merge()` removes duplicate identifiers by namespace and normalized value.

### Authorship, Venue, And Language

`OrcidId` validates ORCID strings in the form `0000-0000-0000-000X` or `0000-0000-0000-0000`.

`Author` stores family name, optional given name, optional ORCID, and a normalized full name. Author identity first uses matching ORCID values when both sides have ORCID, then falls back to normalized full-name equality.

`AuthorList` is an immutable ordered collection with `first()`, `last()`, `get()`, `count()`, `all()`, `isEmpty()`, and `intersect()` helpers.

`Venue` stores venue name, optional ISSN, optional type, and optional publisher. v1 includes `isJournal()` and `isConference()` helpers.

`LanguageCode` validates two-letter ISO-style codes and optional region suffixes such as `en` or `fr-CA`. It also ships helpers for English, French, and Arabic.

### Scholarly Work And Corpus Slice

`ScholarlyWork` is the canonical shared work model used across search, deduplication, screening, citation network, dissemination, persistence, and package commands.

It is created through `ScholarlyWork::reconstitute()` and requires a non-empty title, a `WorkIdSet`, and a source provider. It can also carry authors, year, venue, abstract, citation count, retraction flag, retrieval timestamp, and optional raw provider data.

Important behavior:

- `primaryId()` delegates to `WorkIdSet::primary()`.
- `isSameWorkAs()` compares work identity only through overlapping identifiers, not title matching.
- `mergeWith()` merges identifier sets and fills missing fields from the other work without overwriting existing non-null fields.
- `withRawData()` and `withoutRawData()` return cloned work instances.
- `isPreprint()` is true for arXiv provider records or repository venues.
- `completenessScore()` scores work metadata for representative-election style workflows.

`CorpusSlice` is an immutable-style aggregate for a set of `ScholarlyWork` objects. It deduplicates by `ScholarlyWork::isSameWorkAs()` when works are added or slices are merged.

It supports empty slices, construction from works, adding one work, containment checks, lookup by work ID, lookup by title, count, all, empty check, merge, filter, sort by year, sort by cited-by count, subtract, retracted-only filtering, and excluding retracted works.

`CorpusSliceId` is generated from random bytes and identifies one slice instance.

### Corpus Lock And Snapshot Primitives

`CorpusOperation` enumerates operations that may mutate or depend on a project corpus:

- `search`
- `deduplicate`
- `snowball`
- `screen`
- `adjudicate`
- `build_graph`
- `retrieve_full_text`
- `export`

`ProjectLockState` represents a project's lock status, lock/unlock timestamps, actor IDs, and reasons.

`ProjectLockAction` records `locked` and `unlocked` lifecycle actions.

`CorpusSnapshot` represents the immutable snapshot associated with a locked project corpus. It carries snapshot ID, project ID, lock timestamp, work count, optional creator, optional reason, metadata, and optional created timestamp.

`CorpusLockPolicy` centralizes shared lock rules:

- `assertCorpusMutable()` blocks corpus mutation when a project is locked.
- `assertCorpusLocked()` requires a locked project before workflows that depend on final corpus membership.
- `isLocked()` delegates lock lookup to `ProjectLockPort`.
- `assertWorksBelongToProject()` rejects work IDs outside the project corpus.
- `exportMetadata()` returns lock/snapshot metadata and marks output as citable/final only when the project is locked and an immutable snapshot exists.

### Job Lifecycle Primitives

`JobLifecycleStatus` ships four states:

- `started`
- `progressed`
- `completed`
- `failed`

`JobLifecycleRecord` is a readonly value object for job audit records. It includes idempotency key, run ID, job name, job class, status, context, summary, error details, duration, and occurrence timestamp.

Factory methods create consistent records for started, progressed, completed, and failed jobs. Idempotency keys are SHA-256 hashes based on run ID, status, and optional progress key.

### Shared Ports

The shared ports are:

| Port | Purpose |
| --- | --- |
| `ProjectLockPort` | Read whether a project is locked. |
| `ProjectLockLifecyclePort` | Lock, unlock, and inspect project lock state. |
| `ProjectWorkMembershipPort` | Report requested work IDs that are outside a project corpus. |
| `ProjectCorpusWorksPort` | Return authoritative work IDs for a project, using locked snapshots when available. |
| `CorpusSnapshotRepositoryPort` | Create and read immutable corpus snapshots. |
| `JobLifecycleRecorderPort` | Record job lifecycle records, normally by idempotency key. |
| `JobLifecycleReaderPort` | Read lifecycle history and latest run status. |
| `TransactionPort` | Run a callback inside an infrastructure transaction. |

These ports let the application layer express shared rules without importing persistence models or framework transaction APIs.

## Public API / Commands

The shared kernel has no package command surface of its own.

The public API is the shared namespace used by other core modules and Laravel package consumers:

- `Nexus\Shared\Domain\ScholarlyWork`
- `Nexus\Shared\Domain\CorpusSlice`
- `Nexus\Shared\ValueObject\WorkIdNamespace`
- `Nexus\Shared\ValueObject\WorkId`
- `Nexus\Shared\ValueObject\WorkIdSet`
- `Nexus\Shared\ValueObject\Author`
- `Nexus\Shared\ValueObject\AuthorList`
- `Nexus\Shared\ValueObject\OrcidId`
- `Nexus\Shared\ValueObject\Venue`
- `Nexus\Shared\ValueObject\LanguageCode`
- `Nexus\Shared\ValueObject\CorpusOperation`
- `Nexus\Shared\ValueObject\ProjectLockState`
- `Nexus\Shared\ValueObject\ProjectLockAction`
- `Nexus\Shared\ValueObject\CorpusSnapshot`
- `Nexus\Shared\ValueObject\JobLifecycleStatus`
- `Nexus\Shared\ValueObject\JobLifecycleRecord`
- `Nexus\Shared\Application\CorpusLockPolicy`
- shared ports under `Nexus\Shared\Port`

The shared kernel should not expose provider-specific client APIs, database query APIs, framework model APIs, HTTP routes, or UI contracts.

## Data Model And Persistence

The shared kernel does not own database tables directly. It defines domain and value objects that persistence adapters serialize into package-owned migrations.

Important persistence-facing rules:

- `ScholarlyWork` uses `WorkIdSet` for external and internal identifiers.
- `internal` work identifiers are part of the shipped namespace enum and are used by persistence-backed workflows.
- `CorpusSnapshot` is the domain value returned when a locked project has immutable corpus membership.
- `ProjectLockState` and `ProjectLockAction` represent lock lifecycle state and history.
- `JobLifecycleRecord` and `JobLifecycleStatus` represent job audit records.
- ports define the persistence contract; adapters decide how rows, joins, IDs, and transactions are implemented.

Domain and application code should depend on shared ports and value objects, not Eloquent models.

## Main Workflows

### Identifier Normalization

Provider and persistence adapters create `WorkId` instances for incoming identifiers. Normalization happens at construction, so equality and overlap checks use normalized values.

### Work Merge

Two `ScholarlyWork` instances are the same work when their `WorkIdSet` values overlap. Merging keeps the base work's existing non-null fields, merges identifiers, fills missing fields from the other work, and preserves raw provider data only when explicitly supplied.

### Corpus Slice Assembly

`CorpusSlice` builds reusable work sets for search results, deduplication input, export input, graph input, and other corpus workflows. Adding or merging works deduplicates by shared identifiers. Slice helpers support lookup, filtering, sorting, subtraction, and retraction-aware views.

### Lock Enforcement

Application handlers use `CorpusLockPolicy` to enforce whether a workflow can mutate the corpus or must operate on a locked corpus. The policy delegates storage concerns to lock, membership, lifecycle, and snapshot ports.

### Citable Export Metadata

`CorpusLockPolicy::exportMetadata()` returns conservative citable/final metadata. A locked state alone is not enough; citable output requires an immutable corpus snapshot.

### Job Lifecycle Audit

Long-running package workflows can record lifecycle events through `JobLifecycleRecorderPort` and expose read-side history through `JobLifecycleReaderPort`.

## Validation And Tests

The shared kernel is covered by unit tests:

- `tests/Unit/Shared/WorkIdTest.php`
- `tests/Unit/Shared/ValueObjectsTest.php`
- `tests/Unit/Shared/ScholarlyWorkTest.php`
- `tests/Unit/Shared/CorpusLockPolicyTest.php`

The tests cover:

- supported identifier namespaces and lowercase backing values,
- DOI and arXiv normalization,
- `WorkId::fromString()` parsing failures,
- `WorkIdSet` primary precedence, overlap, immutability, merge, and count behavior,
- ORCID and language code validation,
- author identity by ORCID or normalized name,
- venue helpers,
- scholarly work identity, merge, raw data, preprint detection, completeness scoring, and property access,
- corpus slice deduplication, merge, lookup, sort, filter, subtract, and retraction helpers,
- corpus lock policy enforcement,
- citable export metadata only when a locked project has an immutable snapshot.

For code validation, the focused gate is:

```powershell
php -d memory_limit=512M vendor/bin/pest tests/Unit/Shared
```

For release validation, use the broader package gate from the architecture document.

## What Did Not Ship In v1

The shared kernel does not ship:

- provider-specific query or response objects,
- database models or migrations inside `Nexus\Shared`,
- HTTP routes,
- UI state models,
- graph algorithm implementations,
- bibliography serializers,
- screening criteria or screening decision models,
- a title-based scholarly work identity rule,
- an author persistence model,
- full ORCID checksum validation beyond format validation,
- a non-Laravel transaction implementation.

## Changed From Earlier Specs

- The namespace root in the archived spec was shown as `Nexus\Shared`; live code keeps that root under Composer PSR-4 prefix `Nexus\`.
- The identifier namespace enum expanded from seven namespaces to nine: `pmcid` and `internal` now ship.
- Primary identifier precedence now includes `pmcid` before PubMed and `internal` after DOAJ.
- `WorkIdSet::merge()` removes duplicate IDs. The old class spec said duplicate namespace entries were allowed and merge would not remove duplicates.
- `LanguageCode`, project lock values, corpus operation values, corpus snapshots, and job lifecycle values are shipped even though they were not in the earliest shared-kernel spec.
- `ScholarlyWork` and `CorpusSlice` are canonical shared domain objects; earlier designs sometimes treated search-owned work/corpus models as the starting point.
- `CorpusLockPolicy` is a shipped shared application service, not just a deduplication concern.
- `DomainEvent` and `DomainException` exist as contracts, but current shipped project lock exceptions are simple shared exceptions and should be documented from live code rather than inferred from the old base-exception spec.

## Implementation References

- Code references:
  - `composer.json`
  - `src/Shared`
  - `tests/Unit/Shared`