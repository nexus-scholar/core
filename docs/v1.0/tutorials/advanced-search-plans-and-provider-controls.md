# Advanced Search Plans And Provider Controls

This tutorial shows how to build a host command that runs YAML search plans through `nexus-scholar/core`.

You will build a `review:search-plan` command that can:

- parse a YAML plan,
- run all searches or selected ids,
- override project id,
- override providers,
- override max results,
- filter by priority,
- continue after item-level failures,
- print a run summary.

Use this when your host app needs repeatable search batches rather than one ad hoc search term.

## Why Use Core Search Plans

The single-search path uses `SearchAcrossProviders`. A search plan is a higher-level application workflow:

- the plan file describes multiple related searches,
- each item carries provider aliases and metadata,
- runtime options can narrow or override the plan,
- the runner records per-item success or failure.

In Laravel, resolve:

- `SearchPlanParserPort` to parse YAML text into a `SearchPlan`,
- `SearchPlanRunner` to execute selected plan items.

The package service provider binds the parser to the YAML implementation and binds the runner to the configured search executor.

## Create A Search Plan

Create `resources/queries/tomato-review.yml`:

```yaml
project: tomato_review
providers: [openalex, crossref]
include_raw_data: true

searches:
  - id: tomato-segmentation
    label: Tomato segmentation
    query: '"tomato" "segmentation" deep learning'
    limit: 25
    year_from: 2018
    priority: core
    metadata:
      theme: segmentation

  - id: label-efficient-tomato
    label: Label-efficient tomato vision
    query: '"tomato" ("semi-supervised" OR "label-efficient") vision'
    providers: [openalex, semantic_scholar]
    limit: 20
    year_from: 2020
    priority: expansion
    metadata:
      theme: label_efficiency
```

Important fields:

| Field | Scope | Meaning |
| --- | --- | --- |
| `project` | Root | Default project id. |
| `providers` | Root or item | Provider aliases for the search. |
| `include_raw_data` | Root or item | Preserve raw provider payloads. |
| `searches` | Root | List of plan items. `queries` is also accepted. |
| `id` | Item | Stable item id used for selection. |
| `query` | Item | Provider search query text. |
| `limit` | Item | Max provider results. |
| `year_from` / `year_to` | Item | Year range bounds. |
| `priority` | Item | Host-defined selection label. |
| `metadata` | Item | Host-defined metadata carried with the item. |

## Create The Command

```powershell
php artisan make:command ReviewSearchPlan
```

Use the parser interface with `parseString()`. The concrete YAML parser also has helper methods, but the interface is the safer host-app contract.

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Nexus\Search\Application\Plan\SearchPlanParserPort;
use Nexus\Search\Application\Plan\SearchPlanRunOptions;
use Nexus\Search\Application\Plan\SearchPlanRunner;

class ReviewSearchPlan extends Command
{
    protected $signature = 'review:search-plan
        {file : YAML search plan path}
        {--id=* : Only run selected search ids}
        {--priority= : Only run items with this priority}
        {--project= : Override project id}
        {--provider=* : Override provider aliases}
        {--limit= : Override max results}
        {--fail-fast : Stop on first item failure}';

    protected $description = 'Run a Nexus Scholar core YAML search plan.';

    public function handle(
        SearchPlanParserPort $parser,
        SearchPlanRunner $runner,
    ): int {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! File::exists($path)) {
            $this->error("Search plan not found: {$path}");

            return self::FAILURE;
        }

        $plan = $parser->parseString(File::get($path), $path);

        $result = $runner->run($plan, new SearchPlanRunOptions(
            onlyIds: array_values((array) $this->option('id')),
            priority: $this->stringOption('priority'),
            projectId: $this->stringOption('project'),
            maxResults: $this->integerOption('limit'),
            providerAliases: array_values((array) $this->option('provider')),
            continueOnFailure: ! (bool) $this->option('fail-fast'),
        ));

        $this->info('Search plan complete.');
        $this->line('Plan: '.$plan->sourceName);
        $this->line('Items selected: '.count($result->itemResults));
        $this->line('Succeeded: '.$result->successCount());
        $this->line('Failed: '.$result->failureCount());
        $this->line('Raw records: '.$result->totalRaw());
        $this->line('Unique works: '.$result->totalUnique());

        $this->table(
            ['ID', 'Status', 'Works', 'Raw', 'Duration', 'Error'],
            array_map(fn ($item) => [
                $item->item->id,
                $item->succeeded() ? 'ok' : 'failed',
                (string) ($item->result?->corpus->count() ?? 0),
                (string) ($item->result?->totalRaw ?? 0),
                $item->durationMs.' ms',
                $item->error?->getMessage() ?? '',
            ], $result->itemResults),
        );

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        return preg_match('#^[A-Za-z]:\\\\|^/#', $path)
            ? $path
            : base_path($path);
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
```

## Run The Plan

Run every item:

```powershell
php artisan review:search-plan resources/queries/tomato-review.yml
```

Run one item:

```powershell
php artisan review:search-plan resources/queries/tomato-review.yml --id=tomato-segmentation
```

Run only high-priority or expansion searches:

```powershell
php artisan review:search-plan resources/queries/tomato-review.yml --priority=expansion
```

Override provider selection at runtime:

```powershell
php artisan review:search-plan resources/queries/tomato-review.yml --provider=openalex --provider=doaj
```

Override the project id:

```powershell
php artisan review:search-plan resources/queries/tomato-review.yml --project=tomato_review_refresh
```

## Operational Notes

Search is a corpus-mutating operation. Core blocks searches against locked projects, so run search plans before locking the corpus.

If you need a final citable review:

1. Run search plans.
2. Inspect results.
3. Deduplicate or curate as needed.
4. Lock the corpus.
5. Run screening and final exports.

Provider controls are intentionally host-visible. A serious review workflow should record:

- the plan file used,
- selected providers,
- provider credentials posture,
- runtime overrides,
- timestamp,
- project id,
- whether raw provider payloads were preserved.

## What Core Provides

Core provides:

- search-plan parsing,
- search-plan validation,
- item selection,
- provider-aware query DTO construction,
- provider execution,
- per-item failure handling,
- project-mode persistence through the configured search executor.

Your host app provides:

- file locations,
- command names,
- runtime option design,
- console rendering,
- local audit files if you want them.

## Implementation References

- `src/Search/Application/Plan/SearchPlanParserPort.php`
- `src/Search/Application/Plan/SearchPlanRunner.php`
- `src/Search/Application/Plan/SearchPlanRunOptions.php`
- `src/Search/Application/Plan/SearchPlanResult.php`
- `src/Search/Application/Plan/SearchPlanItem.php`
- `src/Search/Infrastructure/Plan/YamlSearchPlanParser.php`
- `docs/v1.0/modules/03-core-search-and-providers.md`