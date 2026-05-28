# Core Architecture And Package Boundary

## Purpose

`nexus-scholar/core` is the reusable Nexus Scholar engine for systematic literature review workflows. In v1.0 it ships as a Laravel package plus framework-light domain and application library.

The package owns reusable scholarly workflow behavior:

- search orchestration across academic providers,
- deduplication and corpus identity,
- corpus locking and snapshot authority,
- screening, adjudication, and screening comparison,
- citation graph construction, analysis, snowballing, and graph export,
- legal open-access full-text retrieval,
- bibliography and network export,
- Laravel package integration, migrations, jobs, events, and read ports.

## v1 Status

Status: shipped in v1.0.0.

The architecture boundary is stable enough for Laravel package consumers:

- Domain and application code are framework-light.
- Laravel-specific code is isolated under `src/Laravel`.
- External systems are reached through ports and infrastructure adapters.
- Package-owned commands are limited to implemented reusable workflows.
- HTTP routes, browser UI, and product-specific workflows are outside the package contract.

## What Shipped

### Layered Package Shape

The package is organized by bounded context:

| Context | Purpose |
| --- | --- |
| `Shared` | Cross-context value objects, corpus slices, scholarly work model, lock state, job lifecycle values, and common ports. |
| `Search` | Query modeling, provider execution, provider adapters, rate limiting, cache identity, YAML plan parsing, and persisted search execution. |
| `Deduplication` | Duplicate policies, cluster assembly, representative election, corpus lock/unlock application services, and cluster persistence ports. |
| `Screening` | Screening criteria, run/decision/vote model, deterministic and LLM-assisted screening, human adjudication, and run comparison. |
| `CitationNetwork` | Citation graph model, graph builders, metrics, shortest paths, snowballing ports, provider traversal, and persistence. |
| `Dissemination` | Bibliography serializers, network serializers, full-text retrieval sources, file storage, fetch audit records, and export history. |
| `Laravel` | Service provider, config, migrations, Eloquent models/repositories, package commands, jobs, events, listeners, and storage bridges. |

### Stable Package Commands

The core package owns two Artisan commands:

- `nexus:search`: executes one term or a YAML search plan against configured providers.
- `nexus:screen`: screens one work or a project corpus through deterministic, LLM, council, or dry-run modes.

Additional workflows must be exposed by consuming applications through their own adapters around the core handlers and ports.

### Stable Application Surface

Laravel package consumers may resolve documented handlers and services from the container, including:

- search execution and search-plan services,
- deduplication and lock/unlock handlers,
- screening, adjudication, and comparison handlers,
- full-text retrieval handlers,
- citation graph, network analysis, shortest path, and snowballing handlers,
- bibliography, network, and citation graph export handlers.

Read-side consumers should use package reader ports instead of direct SQL:

- `ExportHistoryReaderPort`
- `JobLifecycleReaderPort`
- `FullTextFetchReaderPort`

## Public API / Commands

The stable public surface is:

- domain value objects and application DTOs used by documented handlers,
- application handlers and ports resolved from Laravel's container,
- package migrations and published configuration,
- model-backed repository bindings,
- package-owned Artisan commands,
- read ports for export history, job lifecycle, and full-text fetch audit records.

The package does not guarantee stability for private implementation details, Eloquent internals beyond documented repository/read contracts, test fakes, fixture files, or planning documents.

## Data Model And Persistence

The package persists workflow state through Laravel migrations and Eloquent-backed repositories. The important architectural choices are:

- scholarly works use internal UUID row identity;
- provider IDs, DOI, arXiv, OpenAlex, Semantic Scholar, PubMed, IEEE, and similar identifiers are external IDs;
- provider provenance is persisted separately from canonical work identity;
- corpus snapshots represent immutable locked membership;
- export history, full-text fetch audit records, and job lifecycle records have read ports for host-facing inspection;
- package repositories resolve domain identifiers before writing foreign keys.

The persistence layer is an adapter. Domain and application code must depend on ports, not Eloquent models.

## Main Workflows

### Search

Search requests become domain query objects and search plans. Provider selection is immutable per request. Provider calls are rate-limited, provider results normalize into shared scholarly work objects, and persistent execution records query/provider/work provenance.

### Deduplication And Locking

Deduplication applies exact-ID and fuzzy policies before electing a representative work. Corpus locking freezes project membership through a corpus snapshot and blocks corpus mutation operations that would invalidate final/citable output.

### Screening

Screening evaluates work records against criteria using deterministic, LLM, council, or dry-run modes. Screening decisions, votes, runs, adjudication inputs, and run comparisons are application-level behavior backed by repository ports.

### Citation Network

Citation graph workflows build direct citation, co-citation, and bibliographic-coupling graphs. Graph algorithms and exporters are accessed through adapters so the domain model does not expose infrastructure graph types.

### Dissemination

Dissemination covers bibliography export, network export, citation graph export, legal open-access full-text retrieval, file storage, export history, and full-text fetch audit records.

### Laravel Integration

The Laravel layer publishes config and migrations, binds ports, registers package commands, provides package jobs/events/listeners, and adapts storage, persistence, and framework services to the application layer.

## Validation And Tests

The v1 package gate is:

```powershell
composer validate --strict
composer audit --format=plain --abandoned=ignore
composer test
composer analyse
composer format:check
git diff --check
composer archive --format=zip --file=tmp/nexus-scholar-core
```

The architecture is protected by tests that check:

- no framework imports in non-Laravel bounded contexts,
- no empty placeholder PHP files,
- provider integration tests remain fixture-backed,
- search, persistence, screening, citation network, dissemination, and Laravel integration behaviors have unit and feature coverage.

## What Did Not Ship In v1

The core package does not ship:

- package-owned HTTP routes,
- package-owned browser UI,
- a non-Laravel runtime adapter,
- live provider network calls in CI,
- shadow-library full-text sources,
- product-specific workflows,
- a guaranteed public contract for internal Eloquent model details beyond repository and reader ports.

## Changed From Earlier Specs

- Earlier planning docs sometimes treated future workflows as if they were already live. v1 docs must distinguish shipped behavior from proposals.
- Earlier package notes included host-specific concerns. Core docs now describe generic package consumers only.
- Earlier release-readiness docs were written before `v1.0.0` existed. The package is now released; RC-only wording is stale.
- Earlier checklist items about jobs, graph exports, and graph builders are partly superseded by live code and tests.
- UI/product documents are backlog inputs, not part of the core package contract.

## Implementation References

- Code references:
  - `src`
  - `tests`
  - `src/Laravel/NexusServiceProvider.php`
  - `src/Laravel/Migration`
  - `src/Laravel/Command`