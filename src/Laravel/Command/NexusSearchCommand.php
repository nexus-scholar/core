<?php

declare(strict_types=1);

namespace Nexus\Laravel\Command;

use Illuminate\Console\Command;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;

final class NexusSearchCommand extends Command
{
    protected $signature = 'nexus:search {query : The search term}
                            {--max=50 : Maximum number of results to fetch per provider}
                            {--from-year= : Start year for publication date filter}
                            {--to-year= : End year for publication date filter}';

    protected $description = 'Perform a concurrent literature search across all active providers';

    public function handle(SearchAggregatorPort $aggregator): int
    {
        $termText = $this->argument('query');
        $maxStr   = $this->option('max');
        $fromStr  = $this->option('from-year');
        $toStr    = $this->option('to-year');

        $this->info("Starting Nexus scholarly search for: '{$termText}'...");

        $yearRange = null;
        if ($fromStr !== null || $toStr !== null) {
            $from = $fromStr ? (int) $fromStr : null;
            $to   = $toStr ? (int) $toStr : null;
            $yearRange = new YearRange($from, $to);
            $this->line("Filtering by year: " . ($from ?? '*') . " to " . ($to ?? '*'));
        }

        $query = new SearchQuery(
            term:       new SearchTerm($termText),
            maxResults: (int) $maxStr,
            yearRange:  $yearRange,
        );

        $this->output->write("Querying providers... ");
        
        $start = microtime(true);
        $result = $aggregator->aggregate($query);
        $elapsed = round((microtime(true) - $start) * 1000);
        
        $this->output->writeln("Done in {$elapsed}ms");

        // Print provider stats
        $this->newLine();
        $this->info("Provider Statistics:");
        $statRows = [];
        foreach ($result->providerStats as $stat) {
            $status = $stat->skipReason === null ? 'Success' : 'Failed/Skipped';
            $statRows[] = [
                $stat->alias,
                $stat->resultCount,
                $stat->durationMs . 'ms',
                $status,
                $stat->skipReason ?? '-',
            ];
        }
        $this->table(['Provider', 'Results', 'Latency', 'Status', 'Message'], $statRows);

        // Print Corpus Summary
        $this->newLine();
        $this->info("Deduplication Summary:");
        $this->line("Total raw works retrieved: <comment>{$result->totalRaw}</comment>");
        $this->line("Total unique works after deduplication: <comment>{$result->corpus->count()}</comment>");

        if ($result->corpus->isEmpty()) {
            $this->warn("No results found.");
            return self::SUCCESS;
        }

        // Print final top works
        $this->newLine();
        $this->info("Top Deduplicated Works (Sorted by Citation Count):");
        
        $sortedCorpus = $result->corpus->sortByCitedByCount();
        
        $workRows = [];
        $displayLimit = min($sortedCorpus->count(), 15);
        $works = array_slice($sortedCorpus->all(), 0, $displayLimit);

        foreach ($works as $work) {
            $workRows[] = [
                substr($work->title(), 0, 50) . (strlen($work->title()) > 50 ? '...' : ''),
                $work->year() ?? '-',
                $work->citedByCount() ?? '-',
                $work->sourceProvider(),
                $work->primaryId() ? $work->primaryId()->namespace->value . ':' . $work->primaryId()->value : 'None',
            ];
        }

        $this->table(['Title', 'Year', 'Citations', 'Primary Provider', 'Primary ID'], $workRows);

        if ($sortedCorpus->count() > $displayLimit) {
            $this->line("... and " . ($sortedCorpus->count() - $displayLimit) . " more works.");
        }

        return self::SUCCESS;
    }
}
