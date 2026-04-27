<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphId;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Tests\Support\PersistenceFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->repo = app(CitationGraphRepositoryPort::class);
    $this->workRepo = app(WorkRepositoryPort::class);
    $this->project = PersistenceFactory::makeProject();
});

it('returns null for unknown graph id', function () {
    expect($this->repo->findById(new CitationGraphId('unknown')))->toBeNull();
});

it('saves a graph with no edges and retrieves it', function () {
    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $this->repo->save($graph);

    $loaded = $this->repo->findById($graph->id);

    expect($loaded)->not->toBeNull()
        ->and($loaded->id->toString())->toBe($graph->id->toString())
        ->and($loaded->edgeCount())->toBe(0);
});

it('saves a graph with edges and reconstructs all edges', function () {
    $w1 = PersistenceFactory::makeWork(doi: '10.0001/w1');
    $w2 = PersistenceFactory::makeWork(doi: '10.0001/w2');
    $this->workRepo->save($w1);
    $this->workRepo->save($w2);

    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $graph->addWork($w1);
    $graph->addWork($w2);
    $graph->recordCitation($w1->primaryId(), $w2->primaryId());

    $this->repo->save($graph);

    $loaded = $this->repo->findById($graph->id);

    expect($loaded->edgeCount())->toBe(1);
    $edges = $loaded->allEdges();
    expect($edges[0]->citing->equals($w1->primaryId()))->toBeTrue();
    expect($edges[0]->cited->equals($w2->primaryId()))->toBeTrue();
});

it('reconstructed graph has correct node count from edges', function () {
    $w1 = PersistenceFactory::makeWork(doi: '10.0001/n1');
    $w2 = PersistenceFactory::makeWork(doi: '10.0001/n2');
    $this->workRepo->save($w1);
    $this->workRepo->save($w2);

    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $graph->addWork($w1);
    $graph->addWork($w2);
    $graph->recordCitation($w1->primaryId(), $w2->primaryId());

    $this->repo->save($graph);

    $loaded = $this->repo->findById($graph->id);
    expect($loaded->nodeCount())->toBe(2);
});

it('reconstructed graph has correct edge count', function () {
    $w1 = PersistenceFactory::makeWork(doi: '10.0001/e1');
    $w2 = PersistenceFactory::makeWork(doi: '10.0001/e2');
    $this->workRepo->save($w1);
    $this->workRepo->save($w2);

    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $graph->addWork($w1);
    $graph->addWork($w2);
    $graph->recordCitation($w1->primaryId(), $w2->primaryId());

    $this->repo->save($graph);

    $loaded = $this->repo->findById($graph->id);
    expect($loaded->edgeCount())->toBe(1);
});

it('save replaces all edges on second save: removed edge is gone', function () {
    $w1 = PersistenceFactory::makeWork(doi: '10.0001/s1');
    $w2 = PersistenceFactory::makeWork(doi: '10.0001/s2');
    $w3 = PersistenceFactory::makeWork(doi: '10.0001/s3');
    $this->workRepo->save($w1);
    $this->workRepo->save($w2);
    $this->workRepo->save($w3);

    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $graph->addWork($w1); $graph->addWork($w2); $graph->addWork($w3);
    $graph->recordCitation($w1->primaryId(), $w2->primaryId());
    $this->repo->save($graph);

    $updatedGraph = CitationGraph::withId($graph->id, $graph->type, $graph->projectId);
    $updatedGraph->addWork($w1); $updatedGraph->addWork($w3);
    $updatedGraph->recordCitation($w1->primaryId(), $w3->primaryId());

    $this->repo->save($updatedGraph);

    $loaded = $this->repo->findById($graph->id);
    expect($loaded->edgeCount())->toBe(1);
    expect($loaded->allEdges()[0]->cited->equals($w3->primaryId()))->toBeTrue();
});

it('save is idempotent: saving the same graph twice does not duplicate edge rows', function () {
    $w1 = PersistenceFactory::makeWork(doi: '10.0001/i1');
    $w2 = PersistenceFactory::makeWork(doi: '10.0001/i2');
    $this->workRepo->save($w1);
    $this->workRepo->save($w2);

    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $graph->addWork($w1); $graph->addWork($w2);
    $graph->recordCitation($w1->primaryId(), $w2->primaryId());

    $this->repo->save($graph);
    $count = DB::table('citation_edges')->count();

    $this->repo->save($graph);
    expect(DB::table('citation_edges')->count())->toBe($count);
});

it('projectId is persisted and round-tripped correctly', function () {
    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $this->repo->save($graph);

    $loaded = $this->repo->findById($graph->id);
    expect($loaded->projectId)->toBe($this->project->id);
});

it('graph type is persisted and round-tripped correctly', function () {
    $graph = PersistenceFactory::makeCitationGraph($this->project->id, CitationGraphType::CO_CITATION);
    $this->repo->save($graph);

    $loaded = $this->repo->findById($graph->id);
    expect($loaded->type)->toBe(CitationGraphType::CO_CITATION);
});

it('delete removes the graph row and all its edges', function () {
    $w1 = PersistenceFactory::makeWork(doi: '10.0001/d1');
    $w2 = PersistenceFactory::makeWork(doi: '10.0001/d2');
    $this->workRepo->save($w1); $this->workRepo->save($w2);

    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $graph->addWork($w1); $graph->addWork($w2);
    $graph->recordCitation($w1->primaryId(), $w2->primaryId());
    $this->repo->save($graph);

    $this->repo->delete($graph->id);

    $this->assertDatabaseMissing('citation_graphs', ['id' => $graph->id->toString()]);
    $this->assertDatabaseMissing('citation_edges', ['graph_id' => $graph->id->toString()]);
});

it('findByProjectId returns all graphs for a project', function () {
    $g1 = PersistenceFactory::makeCitationGraph($this->project->id);
    $g2 = PersistenceFactory::makeCitationGraph($this->project->id);
    $this->repo->save($g1);
    $this->repo->save($g2);

    $results = $this->repo->findByProjectId($this->project->id);
    expect($results)->toHaveCount(2);
});

it('findByProjectId returns empty array for project with no graphs', function () {
    expect($this->repo->findByProjectId((string) Str::uuid()))->toBe([]);
});

it('toDomain does not reconstruct edges for works not in the work repository', function () {
    $w1 = PersistenceFactory::makeWork(doi: '10.0001/missing_node');
    $w2 = PersistenceFactory::makeWork(doi: '10.0001/exists_node');

    $this->workRepo->save($w1);
    $this->workRepo->save($w2);

    $graph = PersistenceFactory::makeCitationGraph($this->project->id);
    $graph->addWork($w1); $graph->addWork($w2);
    $graph->recordCitation($w1->primaryId(), $w2->primaryId());
    $this->repo->save($graph);

    DB::table('scholarly_works')->where('id', $w1->primaryId()->value)->delete();

    $loaded = $this->repo->findById($graph->id);
    expect($loaded->edgeCount())->toBe(0);
});
