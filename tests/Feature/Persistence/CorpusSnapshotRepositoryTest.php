<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Laravel\Model\WorkProviderModel;
use Nexus\Screening\Application\Port\ScreeningWorkSourcePort;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Tests\Support\PersistenceFactory;

it('creates an immutable corpus snapshot from current project membership on lock', function (): void {
    $project = PersistenceFactory::makeProject('Snapshot Project');
    $query = makeSnapshotQuery($project->id, 'tomato segmentation');
    $work = makeSnapshotWork('snapshot-work-1', 'Tomato segmentation', 'openalex', [
        'doi' => '10.1000/snapshot',
        'openalex' => 'W1000',
    ]);
    linkSnapshotWork($query->id, $work->id, 'openalex', 1);

    app(ProjectLockLifecyclePort::class)->lock(
        projectId: $project->id,
        actorId: 'reviewer-1',
        reason: 'freeze final search corpus',
        metadata: ['source' => 'feature-test'],
    );

    $snapshot = app(CorpusSnapshotRepositoryPort::class)->latestForProject($project->id);

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->projectId)->toBe($project->id)
        ->and($snapshot->lockedAt)->not->toBeNull()
        ->and($snapshot->createdBy)->toBe('reviewer-1')
        ->and($snapshot->lockReason)->toBe('freeze final search corpus')
        ->and($snapshot->workCount)->toBe(1)
        ->and($snapshot->metadata)->toMatchArray(['source' => 'feature-test']);

    $this->assertDatabaseHas('corpus_snapshot_works', [
        'snapshot_id' => $snapshot->id,
        'work_id' => 'snapshot-work-1',
    ]);

    $snapshotWork = DB::table('corpus_snapshot_works')
        ->where('snapshot_id', $snapshot->id)
        ->where('work_id', 'snapshot-work-1')
        ->first();

    expect(json_decode($snapshotWork->search_query_ids, true))->toBe([$query->id])
        ->and(json_decode($snapshotWork->provider_aliases, true))->toBe(['openalex'])
        ->and(json_decode($snapshotWork->provenance, true)['query_works'][0])->toMatchArray([
            'search_query_id' => $query->id,
            'query_text' => 'tomato segmentation',
            'provider_alias' => 'openalex',
            'provider_work_id' => 'openalex-snapshot-work-1',
            'rank' => 1,
        ]);
});

it('uses snapshot membership for locked projects and inferred query membership for drafts', function (): void {
    $project = PersistenceFactory::makeProject('Snapshot Membership Project');
    $query = makeSnapshotQuery($project->id, 'crop segmentation');
    $frozenWork = makeSnapshotWork('frozen-work', 'Frozen Work', 'openalex', [
        'doi' => '10.1000/frozen',
    ]);
    linkSnapshotWork($query->id, $frozenWork->id, 'openalex', 1);

    expect(app(ProjectWorkMembershipPort::class)->missingWorkIds($project->id, [
        'frozen-work',
        'doi:10.1000/frozen',
    ]))->toBe([]);

    app(ProjectLockLifecyclePort::class)->lock($project->id, actorId: 'reviewer-1');

    $lateWork = makeSnapshotWork('late-work', 'Late Work', 'openalex', [
        'doi' => '10.1000/late',
    ]);
    linkSnapshotWork($query->id, $lateWork->id, 'openalex', 2);

    expect(app(ProjectWorkMembershipPort::class)->missingWorkIds($project->id, [
        'frozen-work',
        'doi:10.1000/frozen',
        'late-work',
        'doi:10.1000/late',
    ]))->toBe(['late-work', 'doi:10.1000/late']);

    expect(app(ProjectCorpusWorksPort::class)->workIds($project->id))->toBe(['frozen-work']);

    $works = app(ScreeningWorkSourcePort::class)->forProject($project->id);

    expect(array_map(static fn ($work): string => $work->id, $works))->toBe(['frozen-work']);
});

function makeSnapshotQuery(string $projectId, string $queryText): SearchQueryModel
{
    return SearchQueryModel::create([
        'id' => (string) Str::uuid(),
        'project_id' => $projectId,
        'query_text' => $queryText,
        'max_results' => 50,
        'cache_key' => hash('sha256', $projectId.$queryText),
        'status' => 'completed',
    ]);
}

/**
 * @param  array<string, string>  $identifiers
 */
function makeSnapshotWork(
    string $id,
    string $title,
    string $provider,
    array $identifiers,
): ScholarlyWorkModel {
    $work = ScholarlyWorkModel::create([
        'id' => $id,
        'title' => $title,
        'abstract' => 'Abstract for '.$title,
        'year' => 2025,
        'venue_name' => 'Plant Methods',
        'retrieved_at' => now(),
    ]);

    foreach ($identifiers as $namespace => $value) {
        WorkExternalIdModel::create([
            'id' => (string) Str::uuid(),
            'work_id' => $id,
            'namespace' => $namespace,
            'value' => strtolower($value),
            'is_primary' => $namespace === 'doi',
        ]);
    }

    WorkProviderModel::create([
        'id' => (string) Str::uuid(),
        'work_id' => $id,
        'provider_alias' => $provider,
        'provider_work_id' => $identifiers[$provider] ?? null,
        'metadata' => ['source' => 'test'],
        'last_seen_at' => now(),
    ]);

    return $work;
}

function linkSnapshotWork(string $searchQueryId, string $workId, string $provider, int $rank): void
{
    QueryWorkModel::create([
        'id' => (string) Str::uuid(),
        'search_query_id' => $searchQueryId,
        'work_id' => $workId,
        'provider_alias' => $provider,
        'provider_work_id' => $provider.'-'.$workId,
        'rank' => $rank,
    ]);
}
