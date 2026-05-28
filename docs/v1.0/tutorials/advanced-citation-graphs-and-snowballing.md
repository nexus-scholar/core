# Advanced Citation Graphs And Snowballing

This tutorial shows how to use core citation-network services from a Laravel host.

You will learn how to:

- build citation, co-citation, or bibliographic-coupling graphs,
- analyze a persisted graph,
- export graph artifacts,
- run snowballing from a seed corpus.

Citation graph workflows are analysis workflows. Snowballing is a corpus-growth workflow, so run snowballing before locking a project corpus.

## Build A Citation Graph

Core graph builders need:

- project id,
- works,
- citation reference relationships.

The host decides where reference relationships come from. In many apps, they come from raw provider payloads preserved during search. This example assumes your host can build a `referencesByWorkId` map.

```php
/**
 * @return array<string, list<string>>
 */
private function referencesByWorkId(array $works): array
{
    $references = [];

    foreach ($works as $work) {
        $id = $work->primaryId()?->toString();
        if ($id === null) {
            continue;
        }

        $raw = $work->rawData() ?? [];
        $references[$id] = array_values($raw['references'] ?? []);
    }

    return $references;
}
```

Create a command:

```powershell
php artisan make:command ReviewBuildGraph
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\CitationNetwork\Application\UseCase\BuildCitationGraph;
use Nexus\CitationNetwork\Application\UseCase\BuildCitationGraphHandler;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

class ReviewBuildGraph extends Command
{
    protected $signature = 'review:build-graph
        {--project= : Project ID}
        {--type=citation : citation, co_citation, or bibliographic_coupling}
        {--dry-run : Do not persist the graph}';

    public function handle(
        ProjectCorpusWorksPort $projectWorks,
        WorkRepositoryPort $works,
        BuildCitationGraphHandler $builder,
    ): int {
        $projectId = (string) $this->option('project');
        $workIds = array_map(
            fn (string $id) => new WorkId(WorkIdNamespace::INTERNAL, $id),
            $projectWorks->workIds($projectId),
        );

        $corpusWorks = array_values($works->findManyByIds($workIds));
        $references = $this->referencesByWorkId($corpusWorks);

        $command = match ((string) $this->option('type')) {
            'co_citation' => BuildCitationGraph::coCitation(
                projectId: $projectId,
                works: $corpusWorks,
                referencesByCitingWorkId: $references,
                persist: ! (bool) $this->option('dry-run'),
            ),
            'bibliographic_coupling' => BuildCitationGraph::bibliographicCoupling(
                projectId: $projectId,
                works: $corpusWorks,
                referencesByWorkId: $references,
                persist: ! (bool) $this->option('dry-run'),
            ),
            default => BuildCitationGraph::directCitation(
                projectId: $projectId,
                works: $corpusWorks,
                referencesByCitingWorkId: $references,
                persist: ! (bool) $this->option('dry-run'),
            ),
        };

        $graph = $builder->handle($command);

        $this->info('Graph built.');
        $this->line('Graph: '.$graph->id->toString());
        $this->line('Type: '.$graph->type->value);
        $this->line('Nodes: '.$graph->nodeCount());
        $this->line('Edges: '.$graph->edgeCount());

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<string>>
     */
    private function referencesByWorkId(array $works): array
    {
        $references = [];

        foreach ($works as $work) {
            $id = $work->primaryId()?->toString();
            if ($id === null) {
                continue;
            }

            $raw = $work->rawData() ?? [];
            $references[$id] = array_values($raw['references'] ?? []);
        }

        return $references;
    }
}
```

Run:

```powershell
php artisan review:build-graph --project=tomato_review --type=citation
php artisan review:build-graph --project=tomato_review --type=bibliographic_coupling
```

## Analyze A Persisted Graph

Create:

```powershell
php artisan make:command ReviewAnalyzeGraph
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\CitationNetwork\Application\UseCase\AnalyzeNetwork;
use Nexus\CitationNetwork\Application\UseCase\AnalyzeNetworkHandler;
use Nexus\CitationNetwork\Domain\CitationGraphId;

class ReviewAnalyzeGraph extends Command
{
    protected $signature = 'review:analyze-graph {graph : Citation graph ID} {--no-persist : Do not persist metrics}';

    public function handle(AnalyzeNetworkHandler $analyze): int
    {
        $metrics = $analyze->handle(new AnalyzeNetwork(
            graphId: new CitationGraphId((string) $this->argument('graph')),
            persistMetrics: ! (bool) $this->option('no-persist'),
        ));

        $this->info('Graph analyzed.');
        $this->line('Nodes: '.$metrics->nodeCount);
        $this->line('Edges: '.$metrics->edgeCount);
        $this->line('Density: '.number_format($metrics->density, 6));
        $this->line('Weak components: '.count($metrics->weakComponents));
        $this->line('Strong components: '.count($metrics->stronglyConnectedComponents));

        return self::SUCCESS;
    }
}
```

## Export A Citation Graph

If your host loads a graph through `CitationGraphRepositoryPort`, export it through `ExportCitationGraphHandler`.

```php
use Nexus\CitationNetwork\Domain\CitationGraphId;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Nexus\Dissemination\Application\UseCase\ExportCitationGraph;
use Nexus\Dissemination\Application\UseCase\ExportCitationGraphHandler;
use Nexus\Dissemination\Domain\NetworkExportFormat;

$graph = $graphs->findById(new CitationGraphId($graphId));

$path = $exports->handle(new ExportCitationGraph(
    graph: $graph,
    format: NetworkExportFormat::GRAPHML,
    filename: "exports/graphs/{$graphId}.graphml",
    requestedBy: 'reviewer-1',
));
```

Supported graph export formats:

- `gexf`,
- `graphml`,
- `cytoscape`.

## Run Snowballing

Snowballing discovers new works from citing and referenced works. It mutates corpus membership conceptually, so run it before the project is locked.

Create:

```powershell
php artisan make:command ReviewSnowball
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpus;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpusHandler;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

class ReviewSnowball extends Command
{
    protected $signature = 'review:snowball
        {--project= : Project ID}
        {--provider=* : Provider alias}
        {--depth=1 : Snowball depth}
        {--forward : Fetch citing works}
        {--backward : Fetch referenced works}';

    public function handle(
        ProjectCorpusWorksPort $projectWorks,
        WorkRepositoryPort $works,
        SnowballCorpusHandler $snowball,
    ): int {
        $projectId = (string) $this->option('project');
        $workIds = array_map(
            fn (string $id) => new WorkId(WorkIdNamespace::INTERNAL, $id),
            $projectWorks->workIds($projectId),
        );

        $known = CorpusSlice::fromWorks(...array_values($works->findManyByIds($workIds)));

        $result = $snowball->handle(new SnowballCorpus(
            projectId: $projectId,
            seedCorpus: $known,
            knownCorpus: $known,
            forward: (bool) $this->option('forward') || ! (bool) $this->option('backward'),
            backward: (bool) $this->option('backward'),
            depth: max(1, (int) $this->option('depth')),
            providerAliases: array_values((array) $this->option('provider')),
        ));

        $this->info('Snowballing complete.');
        $this->line('Initial seeds: '.$result->initialSeedCount);
        $this->line('Depth reached: '.$result->depthReached);
        $this->line('Discovered: '.$result->totalDiscoveredCount());
        $this->line('Net new: '.$result->totalNetNewCount());

        $this->table(
            ['Depth', 'Seeds', 'Discovered', 'Net new', 'Provider failures'],
            array_map(fn ($round) => [
                $round->depth,
                $round->inputSeedCount,
                $round->discoveredCount,
                $round->netNewCount,
                $round->providerFailureCount,
            ], $result->rounds),
        );

        return self::SUCCESS;
    }
}
```

Run:

```powershell
php artisan review:snowball --project=tomato_review --provider=openalex --provider=semantic_scholar --depth=1 --forward --backward
```

## Operational Notes

Use this order:

1. Search.
2. Snowball from seed corpus.
3. Persist or review net-new works through your host workflow.
4. Lock the project corpus.
5. Build graphs and exports against frozen membership.

Core snowballing returns `newCorpus`; your host still needs to decide how to persist, review, or discard those discovered works. Do not hide this decision. Snowballing can change the review boundary.

## What Core Provides

Core provides:

- graph domain objects,
- graph builders,
- graph persistence,
- graph algorithms through ports,
- graph export serializers,
- shortest path support,
- snowballing provider ports,
- provider failure accounting,
- deduplication of discovered works.

Your host provides:

- source of citation relationships,
- graph command UX,
- snowballing policy,
- persistence strategy for net-new works,
- reports and files.

## Implementation References

- `src/CitationNetwork/Application/UseCase/BuildCitationGraph.php`
- `src/CitationNetwork/Application/UseCase/AnalyzeNetwork.php`
- `src/CitationNetwork/Application/UseCase/SnowballCorpus.php`
- `src/CitationNetwork/Application/UseCase/SnowballCorpusResult.php`
- `src/Dissemination/Application/UseCase/ExportCitationGraph.php`
- `src/Dissemination/Domain/NetworkExportFormat.php`
- `docs/v1.0/modules/06-core-citation-network-and-snowballing.md`