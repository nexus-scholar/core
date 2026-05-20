# Test Strategy Plan

Last updated: 2026-05-20

This document defines how to test the Nexus Scholar packages as the code moves from stabilization into reusable search orchestration, citation-network work, jobs, PDF retrieval, and release readiness.

## Current Validation Baseline

Current repository state on 2026-05-20:

- P0 guardrail tests are implemented and should remain permanently green.
- P1 reusable search tests are implemented in `core`; `nexus-cli` has consumer tests for its app-owned search workflow.
- P2 citation graph foundation now has unit, application, and feature coverage for graph construction, graph-package adapters, metrics, shortest paths, weighted persistence, fake-provider snowballing rounds, and Semantic Scholar/OpenAlex/Crossref citation or reference traversal.
- P2 jobs/events now have `SearchJob`, `DeduplicateCorpusJob`, `RetrieveFullTextJob`, and `SnowballJob` implementations with feature tests. Job lifecycle event shapes, progress events, dispatch tests, recorder contract, SQL-backed default recorder, and lifecycle listener are implemented.
- P2 full-text retrieval has handler/source/repository coverage for strict PDF validation, retry, cooldown, deterministic paths, legal OA source resolution, XML artifact storage, and XML text sidecars.
- P3 has a GitHub Actions Pest matrix, but static analysis, formatter checks, and Composer script coverage are still missing.

## Principles

- Write characterization tests before refactors.
- Keep domain tests fast and framework-free.
- Keep application tests focused on orchestration through fake ports.
- Keep Laravel feature tests around bindings, migrations, repositories, commands, jobs, and storage.
- Keep provider integration tests VCR-backed. Do not use live network in CI.
- Every bug found through `nexus-cli` command output should produce a regression test in `core` when the behavior belongs to the package.
- Test the behavior that should be stable, not incidental console formatting.

## Test Layers

### Unit Tests

Purpose:
- Prove pure behavior without Laravel, SQL, queues, or HTTP.

Targets:
- Value objects.
- Domain entities.
- Dedup policies.
- Search query cache identity.
- Provider response normalization.
- Serializers.
- Rate limiter behavior.
- Small algorithm functions.

Examples:
- `SearchQuery` normalizes provider aliases and cache keys.
- `ScholarlyWork` merges missing abstracts from duplicates.
- `WorkId` is the only DOI normalization source.
- `CompletenessElectionPolicy` honors provider priority.
- Graph edge weights survive domain operations.

Rules:
- No `Illuminate\*` imports.
- No Eloquent models.
- No real HTTP.
- No filesystem writes unless the class being tested is a storage adapter.

### Application Tests

Purpose:
- Prove use-case orchestration with fake ports.

Targets:
- Search orchestration.
- Project lock checks.
- Transaction behavior.
- Provider selection.
- Partial provider failure.
- Search plan parsing and running.
- Persistent search recorder calls.
- Corpus lock policy.

Examples:
- `SearchAcrossProvidersHandler` checks `ProjectLockPort` before calling the aggregator.
- Search plan runner continues or stops according to failure policy.
- Selected providers execute without mutating the registered provider collection.
- Search persistence recorder receives query, stats, works, links, and completion events.

Rules:
- Use fakes or spies for ports.
- Do not assert SQL in application tests.
- Prefer result objects over exceptions for expected partial failures.

### Laravel Feature Tests

Purpose:
- Prove package integration with Laravel.

Targets:
- Service provider bindings.
- Migrations.
- Eloquent repositories.
- Commands.
- Jobs.
- Events/listeners.
- Cache/storage adapters.
- Config mapping.

Examples:
- All repository ports resolve from the container.
- `nexus.provider_configs` honors enabled/rate/retry/timeout/API key config.
- Search query persistence round-trips provider aliases.
- PDF fetch audit rows use internal work UUIDs.
- Citation edge weights round-trip through SQL.
- Registered commands match implemented command classes.

Rules:
- Use in-memory SQLite unless a database-specific behavior must be tested.
- Assert actual tables for persistence behavior.
- Avoid snapshotting large command output unless formatting itself is the product.

### Provider Integration Tests

Purpose:
- Prove provider adapters still match real provider response shapes without hitting live services in CI.

Targets:
- OpenAlex.
- Crossref.
- arXiv.
- Semantic Scholar.
- PubMed.
- DOAJ.
- IEEE.

Rules:
- Use PHP-VCR cassettes.
- Do not record credentials in cassettes.
- Use placeholder or test API keys only when a provider requires an API key.
- Sanitize request headers and query params containing secrets.
- Add one fixture per bug class, not one giant fixture per provider.

### Regression Tests

Purpose:
- Make fixed bugs stay fixed.

Required for:
- Provider output issues discovered by running `nexus-cli`.
- Missing abstracts or duplicate merge regressions.
- arXiv query/date filtering.
- IEEE API key/config behavior.
- Search persistence/provenance gaps.
- Laravel command crashes.
- Cache serialization issues.
- Work ID/internal ID mismatches.

Format:
- Test name should describe the bug in behavior terms.
- If the bug came from CLI output, include a small fixture that reproduces the data shape.
- Put the regression in `core` if the package owns the behavior.
- Put the regression in `nexus-cli` if the app owns the file layout or wiki/run JSON behavior.

## Priority Test Map

### P0 Guardrails

Status: implemented, keep green.

Tests:
- Framework boundary test.
- No empty PHP placeholder test.
- Work repository internal UUID/external ID tests.
- Work provider provenance tests.
- Provider config tests.
- PDF fetch SQL tests.
- Citation edge weight SQL tests.

Run:

```powershell
php -d memory_limit=512M vendor/bin/pest tests/Unit/Architecture tests/Feature/Persistence
```

### P1 Search Reuse

Status: implemented.

Implemented tests:
- Command registration feature test.
- YAML search plan parser unit tests.
- Search plan runner application tests.
- Provider alias validation tests.
- CLI `--providers` feature test.
- Persistent search recorder application test.
- SQL feature test for a full persisted search trace.
- `nexus-cli` consumer search command tests.

Key assertions:
- Disabled providers are not executed.
- Unknown providers fail validation with a useful message.
- Provider rate limits and timeouts come from config.
- Search persistence records query, provider stats, works, external IDs, source providers, and query-work provenance.
- Re-running search does not duplicate persisted work identity rows.

Suggested focused command:

```powershell
php -d memory_limit=512M vendor/bin/pest tests/Unit/Search tests/Feature/Persistence/SearchQueryRepositoryTest.php
```

Current focused validation:

```powershell
php -d memory_limit=512M vendor/bin/pest tests/Unit/Search
php -d memory_limit=512M vendor/bin/pest tests/Feature
composer test
```

### P2 Citation Network

Status: partially implemented.

Implemented tests:
- `CitationGraph` duplicate identity and missing-work invariants.
- Direct citation graph building from provider reference IDs.
- Co-citation and bibliographic-coupling graph building with weighted edges.
- Adapter mapping from Nexus graphs to `Mbsoft\Graph\Domain\Graph`.
- PageRank, degree metrics, K-core, connected components, and shortest paths through graph packages.
- Application handlers for build, analyze, and shortest path use cases.
- SQL persistence for graph metadata and weighted edges.
- Snowballing application handler tests for forward rounds, depth progression, already-known vs net-new counts, provider failure isolation, provider alias validation, and Laravel container binding.
- Semantic Scholar snowballing tests for citation endpoint traversal, reference endpoint traversal, supported identifier detection, provider API-key headers, timeout propagation, and Laravel provider registration.
- OpenAlex snowballing tests for forward `cites` traversal, backward `referenced_works` traversal, seed resolution by DOI, timeout propagation, polite-pool mailto propagation, and unsupported seed handling.
- Crossref snowballing tests for backward DOI reference traversal, forward traversal being intentionally unsupported, reference normalization, timeout propagation, polite-pool mailto propagation, and unsupported seed handling.

Tests still to add:
- `CitationGraph` duplicate edge behavior.
- Dangling edge policy.
- Additional real provider citation traversal beyond Semantic Scholar, OpenAlex, and Crossref only where supported by reliable public APIs.
- Rebuild/recompute policy for persisted graph metrics.
- Performance guard for co-citation and bibliographic coupling builders.

Key assertions:
- Graph algorithms do not depend on Laravel.
- Graph building records provider failures without corrupting completed edges.
- Weighted graph exports preserve IDs and weights when serializers support edge export.

### P2 PDF And Full Text

Status: partially implemented.

Implemented tests:
- Handler attempts registered sources and records outcomes.
- Direct PDF URL source resolves explicit raw metadata fields and ignores generic landing-page URLs.
- arXiv, OpenAlex, and Semantic Scholar source behavior has package coverage.
- Unpaywall resolves best and fallback OA PDF URLs, preserves OA metadata, skips non-OA results, and rejects missing email config without HTTP calls.
- PMC OAI resolves reusable PMCID XML candidates and skips missing/error responses.
- Europe PMC resolves open PDF links, falls back to full-text XML when available, and skips non-OA responses.
- PDF fetch persistence writes rows by internal work UUID.
- Existing successful fetches short-circuit source resolution, downloads, storage, and duplicate audit rows when the file still exists.
- Non-PDF downloads are audited as failed attempts and do not prevent later sources from succeeding.
- Download validation checks the `%PDF-` signature and reported content type before storage.
- XML candidates are validated, stored as `.xml`, and paired with extracted `.txt` sidecars in audit metadata.
- Invalid XML candidates are audited as failures and do not prevent later PDF sources from succeeding.
- Download retries are attempted before one source failure is audited.
- Oversized PDF payloads are rejected before storage.
- Failed source cooldown skips recently failed source URLs and is backed by SQL audit lookups.
- Deterministic PDF storage paths sanitize unsafe work IDs, source aliases, and destination folders.
- Deterministic full-text paths also cover XML artifacts and extracted text sidecars.

Tests still to add:
- Non-Laravel storage adapter behavior if core needs filesystem use outside Laravel hosts.
- Re-fetch policy behavior once product rules define when stale successful artifacts should be refreshed.

Key assertions:
- One row is written per attempted source.
- Existing successful path is reused when the file exists.
- Failed first source does not prevent a later successful source.
- Non-PDF response does not get stored as a successful PDF.
- XML response is parsed before storage and rejected if malformed or actually HTML.
- XML text sidecar paths are recorded in metadata.
- Retry exhaustion does not create duplicate audit rows for the same source attempt.
- Recent failed source cooldown avoids repeating known-bad source URLs.
- Generated storage paths are portable and do not leak DOI slashes into nested paths.

### P2 Jobs And Events

Status: partially implemented.

Implemented tests:
- `SearchJob` serialization round-trip keeps only the search plan payload.
- `SearchJob` resolves `SearchPlanRunner` from the Laravel container and executes through `SearchExecutorPort`.
- `DeduplicateCorpusJob` serialization round-trip keeps only corpus, project, and policy-alias payload.
- `DeduplicateCorpusJob` resolves `DeduplicateCorpusHandler` from the Laravel container and executes with fake policies.
- `RetrieveFullTextJob` serialization round-trip keeps only work and destination-folder payload.
- `RetrieveFullTextJob` resolves `RetrieveFullTextHandler` from the Laravel container and executes with fake source, storage, downloader, and repository ports.
- `SnowballJob` serialization round-trip keeps only the `SnowballCorpus` payload.
- `SnowballJob` resolves `SnowballCorpusHandler` from the Laravel container and executes with fake snowballing providers and deduplication.
- `SnowballJob` dispatches `NexusJobProgressed` records for snowball rounds and provider stats after the application handler returns.
- `NexusJobStarted`, `NexusJobProgressed`, `NexusJobCompleted`, and `NexusJobFailed` serialize lifecycle payloads.
- Implemented jobs dispatch started/completed events on success and failed events before rethrowing job failures.
- `RecordNexusJobLifecycle` maps lifecycle events to `JobLifecycleRecord` values through `JobLifecycleRecorderPort`.
- `RecordNexusJobLifecycle` records progress events through the same lifecycle recorder without a separate table.
- `JobLifecycleRecord` idempotency keys are stable for repeated run/status/progress-key pairs.
- `EloquentJobLifecycleRecorder` is the default binding and upserts `job_lifecycle_records` rows by lifecycle idempotency key.

Tests to add:
- Job payloads continue to contain IDs/DTOs, not service instances.
- Additional provider-progress events when other use cases expose meaningful intermediate progress.
- Lifecycle recorder queries by `project_id`, `work_id`, `job_name`, and `status` remain efficient as host apps build progress screens.

Key assertions:
- Jobs can be queued safely.
- Retrying jobs does not duplicate persisted rows.
- Failures include enough context for user-facing status.

### P3 Release Readiness

Status: mostly pending.

Current checks:
- GitHub Actions runs Pest across PHP `8.4`/`8.5` and Laravel `12.*`/`13.*`.

Tests/checks to add:
- Static analysis.
- Format check.
- Composer validate.
- Composer scripts for local parity with CI.
- Package install smoke test.
- Optional Laravel app consumer smoke test.

Suggested composer scripts:

```json
{
  "scripts": {
    "test": "pest",
    "test:unit": "pest tests/Unit",
    "test:feature": "pest tests/Feature",
    "analyse": "phpstan analyse",
    "format": "pint",
    "format:check": "pint --test"
  }
}
```

## CI Plan

Core package workflow:

1. Checkout.
2. Install Composer dependencies.
3. `composer validate --strict --no-ansi`.
4. Run unit tests.
5. Run feature tests.
6. Run provider integration tests with VCR replay only.
7. Run static analysis.
8. Run format check.

Provider integration rule:
- CI must fail if a provider test attempts live network.
- Cassettes must be committed only after secret scrub.

Future consumer workflow:
- Checkout `nexus-cli`.
- Install local/path or dev branch version of `core`.
- Run `composer test`.
- Run one search command smoke test using VCR/fake providers or a small local fixture.

## Fixture Strategy

Provider fixtures:
- Keep cassettes small and scenario-specific.
- Name by behavior, not by date.
- Include edge cases:
  - missing abstract,
  - missing author list,
  - multiple external IDs,
  - no DOI but provider ID present,
  - provider error response,
  - rate limit response,
  - pagination token.

Domain fixtures:
- Prefer builders/factories in tests over large JSON when the behavior is domain-only.
- Use explicit DOI/provider IDs in tests so identity expectations are obvious.

CLI fixtures:
- Keep representative YAML files in test fixtures.
- Keep run JSON minimal and stable.
- Host-specific wiki/screening/PDF fixtures belong in `nexus-cli`.

## Review Checklist For New Tests

Before merging a change, ask:

- Does the test live in the repo that owns the behavior?
- Does it fail before the fix?
- Does it avoid live network and local machine paths?
- Does it prove behavior rather than implementation noise?
- Does it cover the unhappy path if the change handles failures?
- Does it preserve provider provenance and internal/external ID separation when persistence is involved?
- Does it keep Laravel out of non-Laravel layers?

## Recommended First Test Tasks For A New Developer

1. Add corpus lock lifecycle tests before enforcing locked-corpus mutation rules.
2. Add lifecycle progress query tests once a read-side API is introduced for host apps.
3. Add export history persistence tests once the export table/API is defined.
4. Add MIME/signature validation tests for any future downloader behavior changes.
5. Add duplicate successful fetch avoidance tests for `RetrieveFullTextHandler` if the handler grows new source fan-out behavior.
