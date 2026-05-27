# Public API Contracts For 1.0

Last updated: 2026-05-27

This document defines the stable public surface expected for the first `1.0.0` release of `nexus-scholar/core`.

## Package Boundary

`core` is a Laravel package plus framework-light domain/application library. Its stable public surface is:

- domain value objects and application commands/results used by the documented handlers,
- application handlers and ports resolved from Laravel's container,
- Laravel package migrations, configuration, model-backed repository bindings, and package-owned Artisan commands,
- host read ports for export history, job lifecycle, and full-text fetch audit records.

`core` does not own HTTP routes, browser UI, or product-specific CLI workflows. Host applications such as `nexus-scholar/nexus-cli` own those adapters and must keep them thin over `core` handlers and ports.

## Stable Package Commands

The package owns these commands:

- `nexus:search`: execute one term or a YAML search plan against configured providers.
- `nexus:screen`: screen one work or a project corpus through deterministic, LLM, council, or dry-run modes.

The commands must keep stable option names, validation behavior, and machine-readable failure modes after `1.0.0`. Additional host commands are allowed outside the package.

## Stable Write APIs

Laravel hosts may resolve these write-side services from the container:

- `Nexus\Search\Application\Port\SearchExecutorPort`
- `Nexus\Search\Application\Plan\SearchPlanRunner`
- `Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler`
- `Nexus\Deduplication\Application\DeduplicateCorpusHandler`
- `Nexus\Deduplication\Application\LockCorpusHandler`
- `Nexus\Deduplication\Application\UnlockCorpusHandler`
- `Nexus\Screening\Application\UseCase\ScreenCorpusHandler`
- `Nexus\Screening\Application\UseCase\ScreenWorkHandler`
- `Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsHandler`
- `Nexus\Screening\Application\UseCase\CompareScreeningRunsHandler`
- `Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler`
- `Nexus\CitationNetwork\Application\UseCase\BuildCitationGraphHandler`
- `Nexus\CitationNetwork\Application\UseCase\AnalyzeNetworkHandler`
- `Nexus\CitationNetwork\Application\UseCase\FindShortestCitationPathHandler`
- `Nexus\CitationNetwork\Application\UseCase\SnowballCorpusHandler`
- `Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler`
- `Nexus\Dissemination\Application\UseCase\ExportNetworkHandler`
- `Nexus\Dissemination\Application\UseCase\ExportCitationGraphHandler`

## Stable Read APIs

Hosts should use reader ports instead of direct SQL reads:

- `Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort`
- `Nexus\Shared\Port\JobLifecycleReaderPort`
- `Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort`

These readers provide the production-safe query surface for host dashboards, HTTP controllers, CLI presentation commands, and smoke checks.

## Corpus Lock Contract

The project lock controls whether workflows can mutate the corpus or must read from a frozen snapshot.

| State | Allowed operations | Blocked operations | Read authority |
| --- | --- | --- | --- |
| Draft/unlocked | `search`, `deduplicate`, `snowball`, draft screening, draft export | final/citable export | query-work membership |
| Locked with snapshot | `screen`, `adjudicate`, `build_graph`, `retrieve_full_text`, final/citable export | `search`, `deduplicate`, `snowball` corpus mutation | latest immutable corpus snapshot |

Handlers and hosts must route lock checks through `CorpusLockPolicy`, `ProjectWorkMembershipPort`, and `CorpusSnapshotRepositoryPort`. Final/citable exports are valid only when lock metadata includes a corpus snapshot ID.

## Provider Configuration Contract

Provider credentials and contact metadata are host configuration. Published config must not ship real or placeholder credentials. `NEXUS_MAIL_TO` and `NEXUS_UNPAYWALL_EMAIL` should be set by production hosts when provider etiquette or API policy requires contact metadata.

Built-in provider integration tests must remain fixture-backed in CI. Live provider checks are explicit maintenance tasks, not default release gates.

## 1.0 Gate

Before tagging `1.0.0`, the release branch must pass:

```powershell
composer validate --strict
composer audit --format=plain --abandoned=ignore
composer test
composer analyse
composer format:check
git diff --check
composer archive --format=zip --file=tmp/nexus-scholar-core
```

The `nexus-scholar/nexus-cli` host must also pass its command suite against the tagged package constraint intended for release.

## Non-Goals For 1.0

- A non-Laravel runtime.
- Package-owned HTTP routes.
- Package-owned browser UI.
- Live provider network calls in CI.
- Shadow-library full-text sources.
