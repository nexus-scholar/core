<?php

/**
 * Batch runner: reads every query from queries.yml and runs nexus:search on each.
 * Usage:  php test_cmd_batch.php
 *         php test_cmd_batch.php --only=AG_SAM01,TR_WS01
 *         php test_cmd_batch.php --priority=high
 *         php test_cmd_batch.php --dry-run
 *
 * Output is echoed live AND written to storage/run_<timestamp>.log
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

// ─── CLI options ──────────────────────────────────────────────────────────────

$opts      = getopt('', ['only:', 'priority:', 'dry-run', 'max:', 'log-dir:']);
$onlyIds   = isset($opts['only'])     ? explode(',', $opts['only'])     : [];
$priority  = $opts['priority']        ?? null;
$dryRun    = array_key_exists('dry-run', $opts);
$maxOvr    = isset($opts['max'])      ? (int) $opts['max']              : null;
$logDir    = $opts['log-dir']         ?? __DIR__ . '/storage';

// ─── Logging ──────────────────────────────────────────────────────────────────

@mkdir($logDir, 0755, true);
$logFile = $logDir . '/run_' . date('Ymd_His') . '.log';
$logFh   = fopen($logFile, 'w');

function out(string $text, $fh): void
{
    echo $text;
    fwrite($fh, $text);
}

// ─── Load queries ─────────────────────────────────────────────────────────────

$yaml    = Yaml::parseFile(__DIR__ . '/queries.yml');
$queries = $yaml['queries'] ?? [];
$project = $yaml['project'] ?? 'nexus_slr';

// Apply filters
if (!empty($onlyIds)) {
    $queries = array_filter($queries, fn ($q) => in_array($q['id'], $onlyIds, true));
}
if ($priority !== null) {
    $queries = array_filter($queries, fn ($q) => ($q['metadata']['priority'] ?? '') === $priority);
}
$queries = array_values($queries);

if (empty($queries)) {
    out("No queries matched the given filters.\n", $logFh);
    exit(1);
}

// ─── Header ───────────────────────────────────────────────────────────────────

$separator = str_repeat('═', 72) . "\n";
$header    = $separator
    . "  NEXUS SCHOLARLY — BATCH RUN\n"
    . "  Project : {$project}\n"
    . "  Queries : " . count($queries) . " / " . count($yaml['queries']) . "\n"
    . "  Mode    : " . ($dryRun ? 'DRY-RUN (no network calls)' : 'LIVE') . "\n"
    . "  Log     : {$logFile}\n"
    . "  Started : " . date('Y-m-d H:i:s') . "\n"
    . $separator . "\n";

out($header, $logFh);

if ($dryRun) {
    foreach ($queries as $q) {
        out(sprintf(
            "[DRY-RUN] Would run: %s | \"%s\" | %d-%d | max=%d\n",
            $q['id'],
            mb_substr($q['text'], 0, 60),
            $q['year_min'],
            $q['year_max'],
            $maxOvr ?? $q['max_results']
        ), $logFh);
    }
    fclose($logFh);
    exit(0);
}

// ─── Stats accumulator ────────────────────────────────────────────────────────

$summary = [];

// ─── Run each query ───────────────────────────────────────────────────────────

foreach ($queries as $index => $q) {
    $num   = $index + 1;
    $total = count($queries);
    $max   = $maxOvr ?? $q['max_results'];

    $qHeader = $separator
        . sprintf("  [%d/%d] %s — %s\n", $num, $total, $q['id'], $q['metadata']['theme'])
        . sprintf("  Priority : %s\n", $q['metadata']['priority'])
        . sprintf("  Years    : %d – %d  |  max=%d\n", $q['year_min'], $q['year_max'], $max)
        . sprintf("  Term     : %s\n", trim($q['text']))
        . $separator;

    out($qHeader, $logFh);

    // Fresh app container per query to avoid state bleed between runs
    $app = \Orchestra\Testbench\Foundation\Application::create(
        basePath: __DIR__,
        options: [
            'extra' => [
                'providers' => [\Nexus\Laravel\NexusServiceProvider::class],
            ],
        ]
    );

    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

    $startTs = microtime(true);

    $exitCode = $kernel->call('nexus:search', [
        'query'       => trim($q['text']),
        '--from-year' => (string) $q['year_min'],
        '--to-year'   => (string) $q['year_max'],
        '--max'       => (string) $max,
    ]);

    $elapsed  = round((microtime(true) - $startTs) * 1000);
    $output   = $kernel->output();

    out($output, $logFh);

    // Parse totals from command output for the final summary
    $rawCount    = null;
    $uniqueCount = null;

    if (preg_match('/Total raw works retrieved:\s*(\d+)/i', $output, $m)) {
        $rawCount = (int) $m[1];
    }
    if (preg_match('/Total unique works after deduplication:\s*(\d+)/i', $output, $m)) {
        $uniqueCount = (int) $m[1];
    }

    $summary[] = [
        'id'       => $q['id'],
        'theme'    => $q['metadata']['theme'],
        'priority' => $q['metadata']['priority'],
        'raw'      => $rawCount,
        'unique'   => $uniqueCount,
        'ms'       => $elapsed,
        'exit'     => $exitCode,
    ];

    out(sprintf("  ⏱  Completed in %dms  (exit=%d)\n\n", $elapsed, $exitCode), $logFh);

    // Small pause between queries to avoid hammering rate limits
    if ($index < $total - 1) {
        sleep(2);
    }
}

// ─── Final summary table ──────────────────────────────────────────────────────

out($separator, $logFh);
out("  BATCH SUMMARY\n", $logFh);
out($separator, $logFh);

$fmt = "  %-12s %-32s %-8s %6s %6s %8s %5s\n";
out(sprintf($fmt, 'ID', 'Theme', 'Priority', 'Raw', 'Unique', 'Time(ms)', 'OK?'), $logFh);
out("  " . str_repeat('─', 70) . "\n", $logFh);

$totalRaw    = 0;
$totalUnique = 0;
$failures    = 0;

foreach ($summary as $row) {
    $totalRaw    += $row['raw']    ?? 0;
    $totalUnique += $row['unique'] ?? 0;
    if ($row['exit'] !== 0) {
        $failures++;
    }

    out(sprintf(
        $fmt,
        $row['id'],
        mb_substr($row['theme'], 0, 31),
        $row['priority'],
        $row['raw']    ?? '?',
        $row['unique'] ?? '?',
        $row['ms'],
        $row['exit'] === 0 ? '✓' : '✗'
    ), $logFh);
}

out("  " . str_repeat('─', 70) . "\n", $logFh);
out(sprintf(
    "  %-12s %-32s %-8s %6d %6d\n",
    'TOTAL', '', '', $totalRaw, $totalUnique
), $logFh);
out(sprintf("\n  Failures: %d / %d\n", $failures, count($summary)), $logFh);
out("  Finished: " . date('Y-m-d H:i:s') . "\n", $logFh);
out($separator, $logFh);

fclose($logFh);
echo "\nLog saved → {$logFile}\n";
exit($failures > 0 ? 1 : 0);
