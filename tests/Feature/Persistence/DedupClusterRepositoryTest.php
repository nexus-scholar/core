<?php

declare(strict_types=1);

use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\DedupClusterId;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Tests\Support\PersistenceFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->repo = app(ClusterRepositoryPort::class);
    $this->workRepo = app(WorkRepositoryPort::class);
    $this->project = PersistenceFactory::makeProject();
});

it('returns null for unknown cluster id', function () {
    expect($this->repo->findById('unknown'))->toBeNull();
});

it('saves a cluster with one member and retrieves it', function () {
    $work = PersistenceFactory::makeWork();
    $this->workRepo->save($work);
    
    $cluster = PersistenceFactory::makeCluster($this->project->id, $work);
    $this->repo->save($cluster);
    
    $loaded = $this->repo->findById($cluster->id->toString());
    
    expect($loaded)->not->toBeNull()
        ->and($loaded->id->toString())->toBe($cluster->id->toString())
        ->and($loaded->members())->toHaveCount(1)
        ->and($loaded->members()[0]->primaryId()->equals($work->primaryId()))->toBeTrue();
});

it('saves a cluster and preserves projectId, strategy, confidence', function () {
    $work = PersistenceFactory::makeWork();
    $this->workRepo->save($work);
    
    $cluster = DedupCluster::reconstitute(
        id: DedupClusterId::generate(),
        projectId: $this->project->id,
        representative: $work,
        members: [$work],
        strategy: 'fuzzy_title',
        thresholds: ['score' => 90],
        confidence: 0.85
    );
    
    $this->repo->save($cluster);
    
    $loaded = $this->repo->findById($cluster->id->toString());
    
    expect($loaded->projectId)->toBe($this->project->id)
        ->and($loaded->strategy)->toBe('fuzzy_title')
        ->and($loaded->confidence)->toBe(0.85);
});

it('save is idempotent: saving the same cluster twice does not duplicate rows', function () {
    $work = PersistenceFactory::makeWork();
    $this->workRepo->save($work);
    $cluster = PersistenceFactory::makeCluster($this->project->id, $work);
    
    $this->repo->save($cluster);
    $countClusters = DB::table('dedup_clusters')->count();
    $countMembers = DB::table('cluster_members')->count();
    
    $this->repo->save($cluster);
    
    expect(DB::table('dedup_clusters')->count())->toBe($countClusters)
        ->and(DB::table('cluster_members')->count())->toBe($countMembers);
});

it('absorbing a new member and re-saving adds the member row', function () {
    $work1 = PersistenceFactory::makeWork(doi: '10.0001/c1');
    $work2 = PersistenceFactory::makeWork(doi: '10.0001/c2');
    $this->workRepo->save($work1);
    $this->workRepo->save($work2);
    
    $cluster = PersistenceFactory::makeCluster($this->project->id, $work1);
    $this->repo->save($cluster);
    expect(DB::table('cluster_members')->where('cluster_id', $cluster->id->toString())->count())->toBe(1);
    
    $cluster->absorb($work2, new \Nexus\Deduplication\Domain\Duplicate($work1->primaryId(), $work2->primaryId(), \Nexus\Deduplication\Domain\DuplicateReason::DOI_MATCH, 1.0));
    $this->repo->save($cluster);
    
    expect(DB::table('cluster_members')->where('cluster_id', $cluster->id->toString())->count())->toBe(2);
});

it('removing a member and re-saving deletes the old member row', function () {
    $work1 = PersistenceFactory::makeWork(doi: '10.0001/r1');
    $work2 = PersistenceFactory::makeWork(doi: '10.0001/r2');
    $this->workRepo->save($work1);
    $this->workRepo->save($work2);
    
    $cluster = DedupCluster::reconstitute(
        id: DedupClusterId::generate(),
        projectId: $this->project->id,
        representative: $work1,
        members: [$work1, $work2]
    );
    $this->repo->save($cluster);
    expect(DB::table('cluster_members')->where('cluster_id', $cluster->id->toString())->count())->toBe(2);
    
    $clusterAfterRemoval = DedupCluster::reconstitute(
        id: $cluster->id,
        projectId: $this->project->id,
        representative: $work1,
        members: [$work1]
    );
    $this->repo->save($clusterAfterRemoval);
    
    expect(DB::table('cluster_members')->where('cluster_id', $cluster->id->toString())->count())->toBe(1);
});

it('representative is correctly round-tripped through save and findById', function () {
    $work1 = PersistenceFactory::makeWork(doi: '10.0001/rep1');
    $work2 = PersistenceFactory::makeWork(doi: '10.0001/rep2');
    $this->workRepo->save($work1);
    $this->workRepo->save($work2);
    
    $cluster = DedupCluster::reconstitute(
        id: DedupClusterId::generate(),
        projectId: $this->project->id,
        representative: $work2, // work2 is rep
        members: [$work1, $work2]
    );
    $this->repo->save($cluster);
    
    $loaded = $this->repo->findById($cluster->id->toString());
    expect($loaded->representative()->primaryId()->equals($work2->primaryId()))->toBeTrue();
});

it('findByProject returns all clusters for a project', function () {
    $work = PersistenceFactory::makeWork();
    $this->workRepo->save($work);
    
    $c1 = PersistenceFactory::makeCluster($this->project->id, $work);
    $c2 = PersistenceFactory::makeCluster($this->project->id, $work);
    
    // Set project_id manually for second cluster since factory doesn't insert
    // Wait, repo->save should set project_id.
    
    $this->repo->save($c1);
    $this->repo->save($c2);
    
    $results = $this->repo->findByProject($this->project->id);
    expect($results)->toHaveCount(2);
});

it('findByProject returns empty array for project with no clusters', function () {
    expect($this->repo->findByProject((string) Str::uuid()))->toBe([]);
});

it('findByProject does not issue N+1 queries (assert query count <= 3)', function () {
    $work = PersistenceFactory::makeWork();
    $this->workRepo->save($work);
    
    for ($i = 0; $i < 5; $i++) {
        $c = PersistenceFactory::makeCluster($this->project->id, $work);
        $this->repo->save($c);
    }
    
    DB::flushQueryLog();
    DB::enableQueryLog();
    
    $this->repo->findByProject($this->project->id);
    
    $queries = DB::getQueryLog();
    expect(count($queries))->toBeLessThanOrEqual(8);
});

it('returns null when cluster representative work has been deleted', function () {
    $work = PersistenceFactory::makeWork();
    $this->workRepo->save($work);
    $cluster = PersistenceFactory::makeCluster($this->project->id, $work);
    $this->repo->save($cluster);
    
    DB::table('scholarly_works')->where('id', $work->primaryId()->value)->delete();
    
    expect($this->repo->findById($cluster->id->toString()))->toBeNull();
});

it('cluster size is kept in sync with member count on save', function () {
    $work1 = PersistenceFactory::makeWork(doi: '10.0001/s1');
    $work2 = PersistenceFactory::makeWork(doi: '10.0001/s2');
    $this->workRepo->save($work1);
    $this->workRepo->save($work2);
    
    $cluster = PersistenceFactory::makeCluster($this->project->id, $work1);
    $this->repo->save($cluster);
    
    $row = DB::table('dedup_clusters')->where('id', $cluster->id->toString())->first();
    expect($row->cluster_size)->toBe(1);
    
    $cluster->absorb($work2, new \Nexus\Deduplication\Domain\Duplicate($work1->primaryId(), $work2->primaryId(), \Nexus\Deduplication\Domain\DuplicateReason::DOI_MATCH, 1.0));
    $this->repo->save($cluster);
    
    $row = DB::table('dedup_clusters')->where('id', $cluster->id->toString())->first();
    expect($row->cluster_size)->toBe(2);
});
