# Test Strategy Plan

Last updated: 2026-05-20

This document defines how to test the Nexus Scholar packages as the code moves from stabilization into reusable search orchestration, citation-network work, jobs, PDF retrieval, and release readiness.

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

Tests to add:
- `CitationGraph` duplicate edge behavior.
- Dangling edge policy.
- Weighted edges for co-citation/bibliographic coupling.
- Snowball depth validation.
- Snowball round new-vs-known counts.
- Provider citation traversal with fake providers.
- Algorithm fixtures for PageRank, k-core, shortest path, co-citation, and bibliographic coupling.
- Feature tests for graph persistence and retrieval by project.

Key assertions:
- Graph algorithms do not depend on Laravel.
- Graph building records provider failures without corrupting completed edges.
- Weighted graph exports preserve IDs and weights when serializers support edge export.

### P2 PDF And Full Text

Tests to add:
- Direct URL source resolution.
- OpenAlex OA URL resolution.
- Semantic Scholar PDF URL resolution.
- arXiv PDF URL resolution.
- MIME and PDF signature validation.
- Retry behavior.
- Duplicate successful fetch avoidance.
- Failed fetch audit rows.
- Storage path policy.

Key assertions:
- One row is written per attempted source.
- Existing successful path is reused when the file exists.
- Failed first source does not prevent a later successful source.
- Non-PDF response does not get stored as a successful PDF.

### P2 Jobs And Events

Tests to add:
- Job serialization round-trip.
- Job resolves handler from Laravel container.
- Job payload contains IDs/DTOs, not service instances.
- Events are emitted on start, success, provider failure, and final failure.
- Listeners are idempotent.

Key assertions:
- Jobs can be queued safely.
- Retrying jobs does not duplicate persisted rows.
- Failures include enough context for user-facing status.

### P3 Release Readiness

Tests/checks to add:
- Static analysis.
- Format check.
- Composer validate.
- CI matrix.
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

1. Add command registration feature tests for implemented `core` commands.
2. Add YAML search plan parser characterization tests using a trimmed `nexus-cli` query fixture.
3. Add unknown/disabled provider selection tests.
4. Add application tests for a persistent search recorder using fake repositories.
5. Add one SQL feature test for a full persisted search trace.
