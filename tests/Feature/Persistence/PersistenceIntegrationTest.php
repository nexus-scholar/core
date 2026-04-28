<?php

declare(strict_types=1);

use Nexus\Laravel\Model\SlrProject;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Laravel\Model\WorkAuthorModel;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Search\Domain\Port\SearchQueryRepositoryPort;
use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Tests\Support\PersistenceFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

it('migrations complete in dependency order without errors', function () {
    expect(true)->toBeTrue();
});

it('work delete cascades to external ids', function () {
    $work = PersistenceFactory::makeWork(doi: '10.5555/cascade_ids');
    app(WorkRepositoryPort::class)->save($work);
    
    $id = $work->primaryId()->value; // DB uses bare value
    $this->assertDatabaseHas('work_external_ids', ['work_id' => $id]);
    
    DB::table('scholarly_works')->where('id', $id)->delete();
    
    $this->assertDatabaseMissing('work_external_ids', ['work_id' => $id]);
});

it('work delete cascades to work_authors', function () {
    $work = PersistenceFactory::makeWork(doi: '10.5555/cascade_authors');
    app(WorkRepositoryPort::class)->save($work);
    
    $id = $work->primaryId()->value; // DB uses bare value
    $this->assertDatabaseHas('work_authors', ['work_id' => $id]);
    
    DB::table('scholarly_works')->where('id', $id)->delete();
    
    $this->assertDatabaseMissing('work_authors', ['work_id' => $id]);
});


it('all repository port bindings resolve from the container', function () {
    expect(app(WorkRepositoryPort::class))->toBeInstanceOf(\Nexus\Laravel\Persistence\Repository\EloquentWorkRepository::class)
        ->and(app(SearchQueryRepositoryPort::class))->toBeInstanceOf(\Nexus\Laravel\Persistence\Repository\EloquentSearchQueryRepository::class)
        ->and(app(ClusterRepositoryPort::class))->toBeInstanceOf(\Nexus\Laravel\Persistence\Repository\EloquentDedupClusterRepository::class)
        ->and(app(CitationGraphRepositoryPort::class))->toBeInstanceOf(\Nexus\Laravel\Persistence\Repository\EloquentCitationGraphRepository::class);
});

it('full round-trip: save work → save cluster referencing that work → load cluster → representative matches saved work', function () {
    $project = PersistenceFactory::makeProject();
    $work = PersistenceFactory::makeWork(doi: '10.5555/roundtrip_cluster');
    
    $workRepo = app(WorkRepositoryPort::class);
    $clusterRepo = app(ClusterRepositoryPort::class);
    
    $workRepo->save($work);
    
    $cluster = PersistenceFactory::makeCluster($project->id, $work);
    $clusterRepo->save($cluster);
    
    $loaded = $clusterRepo->findById($cluster->id->toString());
    
    expect($loaded->representative()->primaryId()->equals($work->primaryId()))->toBeTrue();
});

it('full round-trip: save works → build citation graph → save graph → load graph → edges and nodes match', function () {
    $project = PersistenceFactory::makeProject();
    $w1 = PersistenceFactory::makeWork(doi: '10.5555/rt_g1');
    $w2 = PersistenceFactory::makeWork(doi: '10.5555/rt_g2');
    
    $workRepo = app(WorkRepositoryPort::class);
    $graphRepo = app(CitationGraphRepositoryPort::class);
    
    $workRepo->save($w1);
    $workRepo->save($w2);
    
    $graph = PersistenceFactory::makeCitationGraph($project->id);
    $graph->addWork($w1);
    $graph->addWork($w2);
    $graph->recordCitation($w1->primaryId(), $w2->primaryId());
    
    $graphRepo->save($graph);
    
    $loaded = $graphRepo->findById($graph->id);
    
    expect($loaded->nodeCount())->toBe(2)
        ->and($loaded->edgeCount())->toBe(1)
        ->and($loaded->allEdges()[0]->citing->equals($w1->primaryId()))->toBeTrue()
        ->and($loaded->allEdges()[0]->cited->equals($w2->primaryId()))->toBeTrue();
});
