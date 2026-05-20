<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Support\Facades\Schema;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\SlrProject;
use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\ProviderStat;
use Nexus\Search\Application\Port\SearchRunRecorderPort;
use Nexus\Search\Domain\Port\SearchQueryRepositoryPort;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Search\Domain\ProviderProgress;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Throwable;

final class EloquentSearchRunRecorder implements SearchRunRecorderPort
{
    public function __construct(
        private readonly SearchQueryRepositoryPort $queries,
        private readonly WorkRepositoryPort $works,
    ) {}

    public function recordStarted(SearchQuery $query): void
    {
        $this->ensureProjectExists($query->projectId);
        $this->queries->save($query);

        SearchQueryModel::where('id', $query->id)->update([
            'status' => 'running',
            'executed_at' => now(),
        ]);
    }

    public function recordProviderStat(SearchQuery $query, ProviderStat $stat): void
    {
        $this->queries->recordProviderProgress(
            searchQueryId: $query->id,
            providerAlias: $stat->alias,
            progress: new ProviderProgress(
                totalRaw: $stat->resultCount,
                totalUnique: $stat->resultCount,
                durationMs: (int) round($stat->latencyMs),
                errorMessage: $stat->skipReason,
            ),
        );
    }

    public function recordWork(
        SearchQuery $query,
        ScholarlyWork $work,
        string $providerAlias,
        string $providerWorkId,
        int $rank,
    ): void {
        $primaryId = $work->primaryId();

        if ($primaryId === null) {
            return;
        }

        $this->works->save($work);
        $this->queries->linkWorkToQuery(
            searchQueryId: $query->id,
            workId: $primaryId->toString(),
            providerAlias: $providerAlias,
            providerWorkId: $providerWorkId,
            rank: $rank,
        );
    }

    public function recordCompleted(SearchQuery $query, AggregatedResult $result): void
    {
        SearchQueryModel::where('id', $query->id)->update([
            'status' => 'completed',
            'total_raw' => $result->totalRaw,
            'total_unique' => $result->corpus->count(),
            'duration_ms' => $result->durationMs,
            'executed_at' => now(),
        ]);
    }

    public function recordFailed(SearchQuery $query, Throwable $error): void
    {
        SearchQueryModel::where('id', $query->id)->update([
            'status' => 'failed',
            'metadata' => ['error' => $error->getMessage()],
            'executed_at' => now(),
        ]);
    }

    private function ensureProjectExists(string $projectId): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        $values = [];
        $columns = Schema::getColumnListing('projects');

        if (in_array('name', $columns, true)) {
            $values['name'] = $projectId;
        }

        if (in_array('description', $columns, true)) {
            $values['description'] = 'Automatically created for Nexus search persistence.';
        }

        SlrProject::query()->firstOrCreate(['id' => $projectId], $values);
    }
}
