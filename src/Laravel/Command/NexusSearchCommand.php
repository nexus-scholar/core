<?php

declare(strict_types=1);

namespace Nexus\Laravel\Command;

use Illuminate\Console\Command;
use Nexus\Search\Application\Plan\SearchPlan;
use Nexus\Search\Application\Plan\SearchPlanItem;
use Nexus\Search\Application\Plan\SearchPlanItemResult;
use Nexus\Search\Application\Plan\SearchPlanRunOptions;
use Nexus\Search\Application\Plan\SearchPlanRunner;
use Nexus\Search\Infrastructure\Plan\YamlSearchPlanParser;
use Throwable;

final class NexusSearchCommand extends Command
{
    protected $signature = 'nexus:search
                            {query? : Inline search term}
                            {--file= : Path to a queries.yml file; runs matching queries sequentially}
                            {--project= : Override project ID}
                            {--max=50 : Maximum results per provider}
                            {--providers= : Comma-separated provider aliases to search}
                            {--from-year= : Start year filter (inline mode only)}
                            {--to-year= : End year filter (inline mode only)}
                            {--priority= : Only run queries with this priority (file mode only)}
                            {--only= : Comma-separated query IDs to run (file mode only)}';

    protected $description = 'Perform a concurrent literature search across active providers';

    public function handle(SearchPlanRunner $runner, YamlSearchPlanParser $parser): int
    {
        try {
            $plan = $this->option('file') !== null
                ? $this->filePlan($parser, (string) $this->option('file'))
                : $this->inlinePlan();

            $options = $this->runOptions($this->option('file') !== null);
            $result = $runner->run($plan, $options);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        if ($result->itemResults === []) {
            $this->warn('No queries matched the given filters.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Search plan: %s | Queries: %d | Project: %s',
            $plan->sourceName,
            count($result->itemResults),
            $options->projectId ?? $plan->projectId,
        ));
        $this->newLine();

        foreach ($result->itemResults as $index => $itemResult) {
            $this->renderItemResult($itemResult, $index + 1, count($result->itemResults));
        }

        $this->newLine();
        $this->info('Batch complete.');
        $this->line(sprintf(
            'Queries: %d | Successful: %d | Failures: %d | Raw: %d | Unique: %d',
            count($result->itemResults),
            $result->successCount(),
            $result->failureCount(),
            $result->totalRaw(),
            $result->totalUnique(),
        ));

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function filePlan(YamlSearchPlanParser $parser, string $path): SearchPlan
    {
        return $parser->parseFile($path);
    }

    private function inlinePlan(): SearchPlan
    {
        $queryText = trim((string) $this->argument('query'));

        if ($queryText === '') {
            throw new \InvalidArgumentException('Provide either a query argument or --file=path/to/queries.yml');
        }

        $projectId = $this->projectOverride() ?? 'default-project';
        $item = new SearchPlanItem(
            id: 'inline',
            label: 'inline',
            query: $queryText,
            projectId: $projectId,
            maxResults: (int) $this->option('max'),
            yearFrom: $this->option('from-year') ? (int) $this->option('from-year') : null,
            yearTo: $this->option('to-year') ? (int) $this->option('to-year') : null,
            providerAliases: $this->providerAliasesFromOption(),
            metadata: ['theme' => 'inline', 'priority' => 'high'],
            priority: 'high',
        );

        return new SearchPlan(
            projectId: $projectId,
            items: [$item],
            sourceName: 'inline',
        );
    }

    private function runOptions(bool $fileMode): SearchPlanRunOptions
    {
        return new SearchPlanRunOptions(
            onlyIds: $fileMode ? $this->onlyIdsFromOption() : [],
            priority: $fileMode ? $this->stringOption('priority') : null,
            projectId: $this->projectOverride(),
            maxResults: $fileMode && $this->option('max') !== '50' ? (int) $this->option('max') : null,
            providerAliases: $this->providerAliasesFromOption(),
            continueOnFailure: true,
        );
    }

    private function renderItemResult(SearchPlanItemResult $itemResult, int $current, int $total): void
    {
        $item = $itemResult->item;
        $this->line(sprintf(
            '<fg=cyan>[%d/%d]</> <options=bold>%s</> - %s',
            $current,
            $total,
            $item->id,
            $item->metadata['theme'] ?? $item->label,
        ));

        if ($item->providerAliases !== []) {
            $this->line('  Providers: ' . implode(', ', $item->providerAliases));
        }

        if (! $itemResult->succeeded()) {
            $this->error('  Failed: ' . $itemResult->error?->getMessage());
            $this->newLine();

            return;
        }

        $result = $itemResult->result;
        if ($result === null) {
            return;
        }

        $this->line('  Querying providers... done in ' . $itemResult->durationMs . 'ms' . ($result->fromCache ? ' (cached)' : ''));

        $statRows = [];
        foreach ($result->providerStats as $stat) {
            $statRows[] = [
                $stat->alias,
                $stat->resultCount,
                $stat->latencyMs . 'ms',
                $stat->skipReason === null ? '<fg=green>OK</>' : '<fg=red>Failed</>',
                $stat->skipReason ?? '-',
            ];
        }
        $this->table(['Provider', 'Results', 'Latency', 'Status', 'Message'], $statRows);

        $this->line(sprintf(
            '  Raw: <comment>%d</comment> -> Unique: <comment>%d</comment>',
            $result->totalRaw,
            $result->corpus->count(),
        ));

        if ($result->corpus->isEmpty()) {
            $this->warn('  No results.');
            $this->newLine();

            return;
        }

        $workRows = [];
        foreach (array_slice($result->corpus->sortByCitedByCount()->all(), 0, 15) as $work) {
            $title = $work->title();
            $workRows[] = [
                mb_substr($title, 0, 48) . (mb_strlen($title) > 48 ? '...' : ''),
                $work->year() ?? '-',
                $work->citedByCount() ?? '-',
                $work->sourceProvider(),
                $work->primaryId()
                    ? $work->primaryId()->namespace->value . ':' . $work->primaryId()->value
                    : 'none',
            ];
        }
        $this->table(['Title', 'Year', 'Cites', 'Provider', 'Primary ID'], $workRows);
        $this->newLine();
    }

    /**
     * @return list<string>
     */
    private function onlyIdsFromOption(): array
    {
        $value = $this->stringOption('only');

        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $id): string => trim($id),
            explode(',', $value),
        )));
    }

    /**
     * @return list<string>
     */
    private function providerAliasesFromOption(): array
    {
        $value = $this->stringOption('providers');

        if ($value === null) {
            return [];
        }

        $aliases = [];
        foreach (explode(',', $value) as $alias) {
            $alias = strtolower(trim($alias));

            if ($alias !== '') {
                $aliases[$alias] = $alias;
            }
        }

        return array_values($aliases);
    }

    private function projectOverride(): ?string
    {
        return $this->stringOption('project');
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
