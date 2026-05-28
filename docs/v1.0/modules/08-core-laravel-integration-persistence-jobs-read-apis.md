# Core Laravel Integration, Persistence, Jobs, And Read APIs

## Purpose

The Laravel integration layer adapts the framework-light core contexts to Laravel applications. It owns service-provider registration, configuration, migrations, Eloquent repositories, package commands, queue jobs, package events, lifecycle listeners, file storage, transaction handling, cache storage, and read-side ports.

This layer is infrastructure. Domain and application code must remain independent of Laravel, Eloquent, Artisan, queue, cache, storage, and event classes.

## v1 Status

Status: shipped in v1.0.0.

The v1 package includes:

- Laravel package auto-discovery,
- config publishing,
- migration loading and publishing,
- service-container bindings for core ports,
- provider configuration registry,
- Eloquent-backed repositories,
- file storage adapter,
- transaction adapter,
- search cache adapter,
- package commands,
- queued jobs,
- job lifecycle events and listener,
- read ports for export history, job lifecycle, and full-text fetch audit records.

## What Shipped

### Service Provider

`NexusServiceProvider` is the integration root. It:

- merges `nexus.php` configuration,
- binds HTTP, storage, cache, transaction, lock, repository, serializer, source, and handler services,
- loads package migrations,
- listens for package job lifecycle events,
- publishes config and migrations,
- registers package commands when running in console.

Composer package auto-discovery registers the provider through the package metadata.

### Configuration

The published config covers:

- provider credentials and rate limits,
- search execution mode and concurrency,
- PDF storage disk,
- full-text source configuration,
- LLM screening provider and model settings,
- council screening settings.

Provider configuration is converted into `ProviderConfigRegistry` at container boot. Disabled providers are excluded from adapter collections.

### Persistence Adapters

The Laravel layer provides Eloquent-backed adapters for:

- projects and project lock state,
- scholarly works,
- authors,
- external work identifiers,
- provider sightings,
- search queries,
- search provider participation,
- query-work membership,
- deduplication clusters,
- citation graphs and edges,
- screening runs, decisions, and votes,
- full-text fetch records,
- export histories,
- corpus snapshots,
- job lifecycle records.

Repositories map between Eloquent rows and domain/application objects. Package consumers should use ports and readers instead of direct model coupling.

### Package Jobs

Queued jobs shipped in v1:

- `SearchJob`,
- `DeduplicateCorpusJob`,
- `SnowballJob`,
- `RetrieveFullTextJob`.

Jobs dispatch package lifecycle events for started, progressed, completed, and failed states. `RecordNexusJobLifecycle` records those events through the lifecycle recorder port.

### Package Commands

The package registers:

- `nexus:search`,
- `nexus:screen`.

These commands expose implemented reusable package workflows. Other application workflows should compose the core handlers and ports from the host application layer.

### Read APIs

The host-facing read ports are:

- `ExportHistoryReaderPort`,
- `JobLifecycleReaderPort`,
- `FullTextFetchReaderPort`.

These ports are intentionally separate from write repositories. They provide stable inspection paths for export records, job lifecycle records, and full-text fetch audit records without exposing internal Eloquent models as the public contract.

## Data Model And Persistence

The package migrations create and update tables for:

- projects,
- authors,
- scholarly works,
- work external ids,
- work provider sightings,
- work authors,
- search queries,
- search query providers,
- query works,
- dedup clusters,
- cluster members,
- screening decisions,
- PDF fetches,
- citation graphs,
- citation edges,
- run checkpoints,
- project lifecycle and lock state,
- conflict records,
- job lifecycle records,
- export histories,
- screening runs,
- screening votes,
- corpus snapshots.

The important v1 persistence rules are:

- framework persistence is an adapter around ports,
- scholarly work rows use internal identity,
- external identifiers are normalized into separate rows,
- search membership and corpus membership are explicit relationships,
- corpus snapshots are immutable lock-time records,
- output and audit records are available through read ports.

## Main Workflows

### Package Boot

1. Composer discovers the service provider.
2. The provider merges package config.
3. Container bindings are registered.
4. Migrations are loaded.
5. Events and commands are registered when applicable.

### Persist Search Results

1. The search runner calls the search handler.
2. Eloquent repositories upsert works, ids, authors, providers, queries, and query-work membership.
3. Search-run recorder stores provider progress and run completion state.

### Run A Queue Job

1. A package job resolves its handler and ports from the container.
2. A started event is emitted.
3. Work is executed.
4. Progress, completion, or failure events are emitted.
5. The lifecycle listener records event state.

### Read Host-Facing Records

1. Consumer resolves one of the read ports.
2. Reader queries package tables.
3. Reader returns stable DTOs or arrays for host inspection.

## Validation And Tests

Laravel integration is covered by:

- service-provider binding tests,
- migration-backed repository tests,
- package command feature tests,
- package job feature tests,
- event/listener tests,
- read API feature tests,
- persistence tests across search, deduplication, screening, citation graphs, full-text fetches, export history, snapshots, and lock state.

Relevant test paths:

- `tests/Feature/Laravel`
- `tests/Feature/Persistence`
- `tests/Unit/ArchitectureTest.php`
- `tests/Unit/NoPlaceholderFilesTest.php`

## What Did Not Ship In v1

The Laravel integration layer does not ship:

- package-owned HTTP routes,
- package-owned browser UI,
- a non-Laravel persistence adapter,
- a public contract for internal Eloquent model classes,
- migration squashing,
- a product-specific workflow shell around every core handler.

Consumers can add those at the application layer while keeping the core package as the reusable engine.

## Changed From Earlier Specs

- The package now ships read ports for export history, job lifecycle, and full-text fetch records.
- Migrations include screening runs and votes, corpus snapshots, job lifecycle records, and export histories.
- `mail_to` now reads from environment configuration without a hard-coded default value.
- Search execution mode and concurrency are configurable.
- Full-text source configuration and LLM screening configuration are centralized in `nexus.php`.
- Package commands are intentionally limited to search and screening.
- Domain and application layers remain protected from framework dependencies by architecture tests.

## Implementation References

- Code references:
  - `src/Laravel/NexusServiceProvider.php`
  - `src/Laravel/config/nexus.php`
  - `src/Laravel/Migration`
  - `src/Laravel/Persistence`
  - `src/Laravel/Job`
  - `src/Laravel/Event`
  - `src/Laravel/Listener`
  - `tests/Feature/Laravel`
  - `tests/Feature/Persistence`