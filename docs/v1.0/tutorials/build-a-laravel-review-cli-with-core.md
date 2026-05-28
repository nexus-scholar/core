# Build A Laravel Review CLI With Nexus Scholar Core

This guide shows how to use `nexus-scholar/core` from a Laravel Artisan application by building a small review CLI. It keeps the example minimal and uses `review:*` command names so it does not collide with package-provided commands.

By the end, the app can:

- search scholarly providers into a project,
- lock the corpus,
- screen a locked project corpus,
- export a bibliography,
- read export history through a core read port.

The point is not to rebuild every command. The point is to learn the package boundary: your Laravel app owns command UX and local workflow choices; `nexus-scholar/core` owns scholarly workflow behavior, persistence contracts, and reusable handlers.

## Before You Start

You need:

- PHP 8.3 or newer for `nexus-scholar/core`.
- A Laravel application.
- Composer.
- A database supported by Laravel. SQLite is fine for local review work.

This tutorial assumes the Laravel app already boots and can run:

```powershell
php artisan about
```

## Install The Package

Require the core package:

```powershell
composer require nexus-scholar/core:^1.0
```

Publish the package config and migrations:

```powershell
php artisan vendor:publish --tag=nexus-config
php artisan vendor:publish --tag=nexus-migrations
php artisan migrate
```

The package service provider is auto-discovered by Laravel. After install, the container can resolve core handlers, repositories, read ports, storage adapters, provider adapters, and lock policy services.

## Configure Providers

Open `config/nexus.php` and set provider credentials through `.env`.

Minimum useful local config:

```dotenv
NEXUS_MAIL_TO=you@example.com

NEXUS_OPENALEX_ENABLED=true
NEXUS_CROSSREF_ENABLED=true
NEXUS_ARXIV_ENABLED=true
NEXUS_DOAJ_ENABLED=true

NEXUS_S2_API_KEY=
NEXUS_PUBMED_API_KEY=
NEXUS_IEEE_API_KEY=
```

IEEE remains disabled unless credentials enable it. Semantic Scholar and PubMed can run without keys at lower rate limits, but serious use should provide keys where possible.

For full-text and export storage later:

```dotenv
FILESYSTEM_DISK=public
NEXUS_PDF_DISK=public
NEXUS_UNPAYWALL_EMAIL=you@example.com
```

For project screening, configure an LLM provider. Without this, core keeps the workflow safe by returning `needs_review` when model screening cannot produce a valid decision.

```dotenv
NEXUS_LLM_SCREENING_ENABLED=true
NEXUS_LLM_OPENROUTER_API_KEY=
NEXUS_LLM_SCREENING_MODEL=openai/gpt-4.1-mini
```

Clear config after changing `.env`:

```powershell
php artisan config:clear
```

## Understand The Core Boundary

Core exposes four kinds of things to a Laravel host:

| Type | Example | Why you use it |
| --- | --- | --- |
| Command DTOs | `SearchAcrossProviders`, `ScreenCorpusCommand`, `LockCorpus` | Describe one use-case request. |
| Handlers | `ScreenCorpusHandler`, `LockCorpusHandler`, `ExportBibliographyHandler` | Execute use cases. |
| Ports | `SearchExecutorPort`, `ProjectCorpusWorksPort`, `ExportHistoryReaderPort` | Stable contracts for host integration. |
| Domain values | `ScreeningCriteria`, `BibliographyFormat`, `CorpusSlice`, `WorkId` | Typed values passed through handlers. |

Your host app should not reach directly into core Eloquent models for normal workflows. Use handlers and read ports. That keeps app code close to the package contract.

## Create A Search Command

Create an app command:

```powershell
php artisan make:command ReviewSearch
```

Use `SearchExecutorPort`, not the lower-level search handler, when you want project-mode persistence. The package binds this port to the persistent runner in Laravel.

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;

class ReviewSearch extends Command
{
    protected $signature = 'review:search
        {query : Search query text}
        {--project=default-project : Project ID}
        {--provider=* : Provider alias, repeatable}
        {--limit=25 : Max results}
        {--year-from= : Start year}
        {--year-to= : End year}
        {--raw : Include raw provider payloads}';

    protected $description = 'Search scholarly providers through Nexus Scholar core.';

    public function handle(SearchExecutorPort $search): int
    {
        $result = $search->handle(new SearchAcrossProviders(
            query: (string) $this->argument('query'),
            projectId: (string) $this->option('project'),
            maxResults: (int) $this->option('limit'),
            yearFrom: $this->integerOption('year-from'),
            yearTo: $this->integerOption('year-to'),
            providerAliases: array_values((array) $this->option('provider')),
            includeRawData: (bool) $this->option('raw'),
        ));

        $this->info('Search complete.');
        $this->line('Works: '.$result->corpus->count());
        $this->line('Raw provider records: '.$result->totalRaw);
        $this->line('Cache hit: '.($result->fromCache ? 'yes' : 'no'));

        $this->table(
            ['ID', 'Year', 'Provider', 'Title'],
            array_map(
                fn ($work) => [
                    $work->primaryId()?->toString() ?? '',
                    (string) ($work->year() ?? ''),
                    $work->sourceProvider(),
                    $work->title(),
                ],
                $result->corpus->all(),
            ),
        );

        return self::SUCCESS;
    }

    private function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
```

Run it:

```powershell
php artisan review:search "semi supervised tomato segmentation" --project=tomatomap --provider=openalex --provider=crossref --limit=10
```

What core does here:

- validates the search term and year range,
- chooses configured providers,
- rate-limits provider calls,
- normalizes provider results into scholarly works,
- deduplicates the returned corpus,
- persists query, provider, work, and query-work provenance for the project.

What your app does:

- chooses the command name,
- chooses the options,
- renders a table,
- decides whether to expose raw provider payloads.

## Add Project Screening

Screening project works should go through `ScreenCorpusHandler`. Create a command:

```powershell
php artisan make:command ReviewScreen
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Screening\Application\UseCase\ScreenCorpusCommand;
use Nexus\Screening\Application\UseCase\ScreenCorpusHandler;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;

class ReviewScreen extends Command
{
    protected $signature = 'review:screen
        {--project= : Project ID}
        {--include=* : Inclusion criterion}
        {--exclude=* : Exclusion criterion}
        {--limit= : Max works to screen}
        {--name= : Screening run name}';

    protected $description = 'Screen a Nexus Scholar project corpus.';

    public function handle(ScreenCorpusHandler $screen): int
    {
        $projectId = trim((string) $this->option('project'));
        if ($projectId === '') {
            $this->error('Provide --project.');

            return self::FAILURE;
        }

        $criteria = ScreeningCriteria::fromArray([
            'include' => array_values((array) $this->option('include')),
            'exclude' => array_values((array) $this->option('exclude')),
        ]);

        $result = $screen->handle(new ScreenCorpusCommand(
            projectId: $projectId,
            criteria: $criteria,
            stage: ScreeningStage::TITLE_ABSTRACT,
            mode: ScreeningRunMode::LLM_SINGLE,
            limit: $this->integerOption('limit'),
            name: $this->option('name') ?: 'Title abstract screening',
        ));

        $this->info('Screening complete.');
        $this->line('Run: '.$result->runId);
        $this->line('Total: '.$result->total);
        $this->line('Include: '.$result->included);
        $this->line('Needs review: '.$result->needsReview);
        $this->line('Exclude: '.$result->excluded);

        return self::SUCCESS;
    }

    private function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
```

Project screening requires a locked project corpus. Create the command now, but run it after the lock step below.

After the corpus is locked, run:

```powershell
php artisan review:screen `
  --project=tomatomap `
  --include="crop image segmentation" `
  --include="label-efficient visual recognition" `
  --exclude="medical imaging" `
  --limit=10 `
  --name="TomatoMAP title abstract smoke"
```

What core does here:

- loads project works through the configured work source,
- requires locked corpus membership,
- creates a screening run,
- records screening decisions,
- records model votes when LLM screening is enabled,
- tracks include, needs-review, and exclude counts.

## Lock The Corpus

Locking freezes project membership into a citable snapshot. Create:

```powershell
php artisan make:command ReviewLockCorpus
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Deduplication\Application\LockCorpus;
use Nexus\Deduplication\Application\LockCorpusHandler;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;

class ReviewLockCorpus extends Command
{
    protected $signature = 'review:lock
        {--project= : Project ID}
        {--actor= : Reviewer or user ID}
        {--reason= : Human-readable lock reason}';

    protected $description = 'Lock a Nexus Scholar project corpus.';

    public function handle(
        LockCorpusHandler $lock,
        CorpusSnapshotRepositoryPort $snapshots,
    ): int {
        $projectId = trim((string) $this->option('project'));
        if ($projectId === '') {
            $this->error('Provide --project.');

            return self::FAILURE;
        }

        $lock->handle(new LockCorpus(
            projectId: $projectId,
            actorId: $this->stringOption('actor'),
            reason: $this->stringOption('reason'),
        ));

        $snapshot = $snapshots->latestForProject($projectId);

        $this->info('Corpus locked.');
        $this->line('Snapshot: '.($snapshot?->id ?? 'none'));
        $this->line('Works: '.($snapshot?->workCount ?? 0));

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
```

Run it:

```powershell
php artisan review:lock --project=tomatomap --actor=reviewer-1 --reason="Final title abstract corpus"
```

After lock, core blocks corpus-mutating operations such as search or snowballing for that project. Review, graph, retrieval, and final export workflows can use the frozen membership.

## Export A Bibliography

Exporting is a good example of host composition. The host chooses the command UX and output path, while core handles corpus metadata, serialization, storage, and export history.

Create:

```powershell
php artisan make:command ReviewExportBibliography
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Dissemination\Application\UseCase\ExportBibliography;
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

class ReviewExportBibliography extends Command
{
    protected $signature = 'review:export-bibliography
        {--project= : Project ID}
        {--format=csv : bibtex, ris, csv, json, or jsonl}
        {--output= : Storage path}
        {--requested-by= : Reviewer or user ID}';

    protected $description = 'Export a project bibliography through Nexus Scholar core.';

    public function handle(
        ProjectCorpusWorksPort $projectWorks,
        WorkRepositoryPort $works,
        ExportBibliographyHandler $exports,
    ): int {
        $projectId = trim((string) $this->option('project'));
        if ($projectId === '') {
            $this->error('Provide --project.');

            return self::FAILURE;
        }

        $format = BibliographyFormat::from(strtolower((string) $this->option('format')));
        $workIds = array_map(
            fn (string $id) => new WorkId(WorkIdNamespace::INTERNAL, $id),
            $projectWorks->workIds($projectId),
        );

        $corpus = CorpusSlice::fromWorks(
            ...array_values($works->findManyByIds($workIds)),
        );

        $path = $exports->handle(new ExportBibliography(
            corpus: $corpus,
            format: $format,
            filename: $this->outputPath($projectId, $format),
            projectId: $projectId,
            requestedBy: $this->stringOption('requested-by'),
        ));

        $this->info('Bibliography exported.');
        $this->line('Works: '.$corpus->count());
        $this->line('Path: '.$path);

        return self::SUCCESS;
    }

    private function outputPath(string $projectId, BibliographyFormat $format): string
    {
        $output = $this->stringOption('output');
        if ($output !== null) {
            return $output;
        }

        return sprintf(
            'exports/%s-%s.%s',
            preg_replace('/[^a-z0-9_-]+/i', '-', $projectId),
            now()->format('Ymd_His'),
            $format->extension(),
        );
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
```

Run it:

```powershell
php artisan review:export-bibliography --project=tomatomap --format=bibtex --requested-by=reviewer-1
```

What core does here:

- serializes the bibliography,
- validates the filename extension,
- writes through the configured storage port,
- records export history,
- includes lock and snapshot metadata when available.

## Read Export History

For host-facing inspection, prefer read ports over direct SQL.

Create:

```powershell
php artisan make:command ReviewExports
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort;

class ReviewExports extends Command
{
    protected $signature = 'review:exports
        {--project= : Project ID}
        {--limit=10 : Max records}';

    protected $description = 'Read Nexus Scholar export history.';

    public function handle(ExportHistoryReaderPort $exports): int
    {
        $records = $exports->latest(
            projectId: $this->stringOption('project'),
            limit: max(1, (int) $this->option('limit')),
        );

        $this->table(
            ['ID', 'Type', 'Format', 'Project', 'Filename', 'Created'],
            array_map(fn ($record) => [
                $record->id,
                $record->type->value,
                $record->format,
                $record->projectId ?? '',
                $record->filename,
                $record->createdAt?->format(DATE_ATOM) ?? '',
            ], $records),
        );

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
```

Run it:

```powershell
php artisan review:exports --project=tomatomap
```

This pattern also applies to:

- `JobLifecycleReaderPort`,
- `FullTextFetchReaderPort`.

## Run The Review Flow

The minimal end-to-end flow is:

```powershell
php artisan review:search "semi supervised tomato segmentation" --project=tomatomap --provider=openalex --limit=20

php artisan review:lock --project=tomatomap --actor=reviewer-1 --reason="Final title abstract corpus"

php artisan review:screen `
  --project=tomatomap `
  --include="crop image segmentation" `
  --exclude="medical imaging" `
  --limit=20

php artisan review:export-bibliography --project=tomatomap --format=bibtex --requested-by=reviewer-1

php artisan review:exports --project=tomatomap
```

That is the smallest useful Laravel-docs-style path for learning core:

- search mutates a draft corpus,
- lock freezes membership,
- screening records review decisions over frozen membership,
- export produces a citable artifact,
- read ports inspect what happened.

## Add Tests

For host commands, test the command behavior, not core internals. Core already owns its own unit and feature tests.

Example test shape:

```php
it('exports a bibliography for a project', function () {
    $this->artisan('review:export-bibliography', [
        '--project' => 'tomatomap',
        '--format' => 'csv',
    ])->assertSuccessful();
});
```

For serious command tests:

- fake provider HTTP or use fixture-backed provider responses,
- seed project works through core repositories,
- assert command output and persisted read-port records,
- avoid live network calls in CI.

## Where To Go Next

A production host application can add practical workflow layers on top of this foundation:

- YAML search plans,
- run JSON files,
- local `storage/runs/latest.json` pointer,
- local wiki ingestion,
- run-file screening mode,
- full-text retrieval commands,
- graph commands,
- read commands for jobs and full-text artifacts,
- release smoke docs.

Those are host-app choices. The durable package lesson remains the same: build commands around core handlers and ports, keep local presentation code in the host, and keep scholarly workflow rules in `nexus-scholar/core`.

## Implementation References

- Core references:
  - `docs/v1.0/modules/01-core-architecture-and-package-boundary.md`
  - `docs/v1.0/modules/03-core-search-and-providers.md`
  - `docs/v1.0/modules/05-core-screening-and-adjudication.md`
  - `docs/v1.0/modules/08-core-laravel-integration-persistence-jobs-read-apis.md`
- Code references:
  - `src/Search/Application/UseCase/SearchAcrossProviders.php`
  - `src/Search/Application/UseCase/PersistentSearchRunner.php`
  - `src/Screening/Application/UseCase/ScreenCorpusCommand.php`
  - `src/Deduplication/Application/LockCorpus.php`
  - `src/Dissemination/Application/UseCase/ExportBibliography.php`
  - `src/Laravel/NexusServiceProvider.php`