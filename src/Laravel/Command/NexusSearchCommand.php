<?php

declare(strict_types=1);

namespace Nexus\Laravel\Command;

use Illuminate\Console\Command;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Symfony\Component\Yaml\Yaml;

final class NexusSearchCommand extends Command
{
    protected $signature = 'nexus:search
                            {query? : Inline search term}
                            {--file= : Path to a queries.yml file — runs all queries sequentially}
                            {--max=50 : Maximum results per provider (overrides YAML value)}
                            {--from-year= : Start year filter (inline mode only)}
                            {--to-year= : End year filter (inline mode only)}
                            {--priority= : Only run queries with this priority (file mode only)}
                            {--only= : Comma-separated query IDs to run (file mode only)}';

    protected $description = 'Perform a concurrent literature search across all active providers';

    public function handle(\Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler $handler): int
    {
        $file = $this->option('file');

        if ($file !== null) {
            return $this->runFile($handler, $file);
        }

        $queryText = $this->argument('query');
        if (empty($queryText)) {
            $this->error('Provide either a query argument or --file=path/to/queries.yml');
            return self::FAILURE;
        }

        return $this->runSingle($handler, [
            'id'          => 'inline',
            'text'        => $queryText,
            'year_min'    => $this->option('from-year') ? (int) $this->option('from-year') : null,
            'year_max'    => $this->option('to-year')   ? (int) $this->option('to-year')   : null,
            'max_results' => (int) $this->option('max'),
            'metadata'    => ['theme' => 'inline', 'priority' => 'high'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function runFile(\Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler $handler, string $path): int
    {
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $yaml    = Yaml::parseFile($path);
        $queries = $yaml['queries'] ?? [];

        // --only filter
        if ($only = $this->option('only')) {
            $ids     = explode(',', $only);
            $queries = array_values(array_filter(
                $queries,
                fn ($q) => in_array($q['id'], $ids, true)
            ));
        }

        // --priority filter
        if ($priority = $this->option('priority')) {
            $queries = array_values(array_filter(
                $queries,
                fn ($q) => ($q['metadata']['priority'] ?? '') === $priority
            ));
        }

        if (empty($queries)) {
            $this->warn('No queries matched the given filters.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Running %d quer%s from %s (project: %s)',
            count($queries),
            count($queries) === 1 ? 'y' : 'ies',
            basename($path),
            $yaml['project'] ?? 'unknown'
        ));
        $this->newLine();

        $summary  = [];
        $failures = 0;

        foreach ($queries as $i => $q) {
            $this->line(sprintf(
                '<fg=cyan>[%d/%d]</> <options=bold>%s</> — %s',
                $i + 1,
                count($queries),
                $q['id'],
                $q['metadata']['theme'] ?? ''
            ));

            // --max overrides YAML per-query value
            if ($this->option('max') !== '50') {
                $q['max_results'] = (int) $this->option('max');
            }

            $exitCode  = $this->runSingle($handler, $q);
            $failures += $exitCode !== self::SUCCESS ? 1 : 0;

            $summary[] = ['id' => $q['id'], 'exit' => $exitCode];

            if ($i < count($queries) - 1) {
                sleep(1);
            }
        }

        $this->newLine();
        $this->info('Batch complete.');
        $this->line(sprintf(
            'Queries: %d  |  Failures: %d',
            count($queries),
            $failures
        ));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function runSingle(\Nexus\Search\Application\UseCase\SearchAcrossProvidersHandler $handler, array $q): int
    {
        $command = new \Nexus\Search\Application\UseCase\SearchAcrossProviders(
            query:      trim((string) $q['text']),
            maxResults: $q['max_results'] ?? 50,
            yearFrom:   $q['year_min'] ?? null,
            yearTo:     $q['year_max'] ?? null,
        );

        $this->output->write('  Querying providers… ');

        $start   = microtime(true);
        $result  = $handler->handle($command);
        $elapsed = round((microtime(true) - $start) * 1000);

        $this->output->writeln("done in {$elapsed}ms" . ($result->fromCache ? ' (cached)' : ''));

        // Provider stats table
        $statRows = [];
        foreach ($result->providerStats as $stat) {
            $statRows[] = [
                $stat->alias,
                $stat->resultCount,
                $stat->latencyMs . 'ms',
                $stat->skipReason === null ? '<fg=green>OK</>' : '<fg=red>Failed</>',
                $stat->skipReason ?? '—',
            ];
        }
        $this->table(['Provider', 'Results', 'Latency', 'Status', 'Message'], $statRows);

        // Dedup summary
        $this->line(sprintf(
            '  Raw: <comment>%d</comment>  →  Unique: <comment>%d</comment>',
            $result->totalRaw,
            $result->corpus->count()
        ));

        if ($result->corpus->isEmpty()) {
            $this->warn('  No results.');
            $this->newLine();
            return self::SUCCESS;
        }

        // Top works
        $sorted = $result->corpus->sortByCitedByCount();
        $works  = array_slice($sorted->all(), 0, 15);

        $workRows = [];
        foreach ($works as $work) {
            $title      = $work->title();
            $workRows[] = [
                mb_substr($title, 0, 48) . (mb_strlen($title) > 48 ? '…' : ''),
                $work->year()         ?? '—',
                $work->citedByCount() ?? '—',
                $work->sourceProvider(),
                $work->primaryId()
                    ? $work->primaryId()->namespace->value . ':' . $work->primaryId()->value
                    : 'none',
            ];
        }
        $this->table(['Title', 'Year', 'Cites', 'Provider', 'Primary ID'], $workRows);

        if ($sorted->count() > 15) {
            $this->line('  … and ' . ($sorted->count() - 15) . ' more works.');
        }

        $this->newLine();
        return self::SUCCESS;
    }
}
