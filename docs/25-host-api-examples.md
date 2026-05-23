# Host API Examples

Last updated: 2026-05-23

This document shows the package-level APIs a Laravel host should resolve from the container. Hosts may add HTTP routes, Artisan commands, queues, or UI, but those surfaces should remain thin adapters around core handlers and reader ports.

## Install And Resolve

```powershell
composer require nexus-scholar/core
php artisan vendor:publish --tag=nexus-config
php artisan vendor:publish --tag=nexus-migrations
php artisan migrate
```

Resolve handlers and read APIs through Laravel DI:

```php
use Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort;
use Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort;
use Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler;
use Nexus\Shared\Port\JobLifecycleReaderPort;

$search = app(SearchAcrossProvidersHandler::class);
$exports = app(ExportHistoryReaderPort::class);
$jobs = app(JobLifecycleReaderPort::class);
$fullText = app(FullTextFetchReaderPort::class);
```

## Write Workflows

Use application handlers for write-side workflows:

- Search: `SearchAcrossProvidersHandler` for in-memory results, or `SearchExecutorPort` / `SearchPlanRunner` for persistent project runs.
- Screening: `ScreenCorpusHandler`, `ScreenWorkHandler`, `AdjudicateScreeningDecisionsHandler`, and `CompareScreeningRunsHandler`.
- Full text: `RetrieveFullTextHandler`; it records fetch audits through `PdfFetchRepositoryPort`.
- Graphs: `BuildCitationGraphHandler`, `AnalyzeNetworkHandler`, and `FindShortestCitationPathHandler`.
- Exports: `ExportBibliographyHandler`, `ExportNetworkHandler`, and `ExportCitationGraphHandler`.

Core enforces corpus lock policy. Hosts enforce user authorization and decide how to present commands, routes, or jobs.

## Read APIs

Use reader ports instead of direct SQL reads:

```php
$latestExports = app(ExportHistoryReaderPort::class)
    ->latest(projectId: 'tomatomap_label_efficiency', type: 'bibliography', limit: 10);

$runEvents = app(JobLifecycleReaderPort::class)
    ->forRun('run-20260523-001');

$latestStatus = app(JobLifecycleReaderPort::class)
    ->latestStatusForRun('run-20260523-001');

$projectArtifacts = app(FullTextFetchReaderPort::class)
    ->forProject('tomatomap_label_efficiency', limit: 100);

$workArtifacts = app(FullTextFetchReaderPort::class)
    ->forWork('doi:10.5555/example', limit: 25);
```

`FullTextFetchReaderPort::forProject()` uses `ProjectCorpusWorksPort`, so locked projects are read from the immutable corpus snapshot and draft projects are read from inferred query-work membership.

## Boundary Notes

The canonical shared scholarly work model is `Nexus\Shared\Domain\ScholarlyWork` with `Nexus\Shared\Domain\CorpusSlice`. Host code should import the Shared classes directly; the old Search-domain work and corpus classes are not retained.

Provider integration tests are fixture-backed. CI must not call live provider networks by default; live provider checks and cassette re-recording are explicit future maintenance tasks.
