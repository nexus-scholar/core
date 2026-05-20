<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Infrastructure\Graph\MbsoftCitationGraphMapper;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

function citationMapperTestWork(string $doi, string $title): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title: $title,
        sourceProvider: 'openalex',
        year: 2024,
        authors: AuthorList::empty(),
        venue: new Venue('Test Journal', 'journal'),
        abstract: 'A test abstract.',
        citedByCount: 5,
    );
}

it('maps citation graphs to directed mbsoft graphs with attributes and weights', function (): void {
    $source = citationMapperTestWork('10.1000/source', 'Source Work');
    $target = citationMapperTestWork('10.1000/target', 'Target Work');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');

    $graph->addWork($source);
    $graph->addWork($target);
    $graph->recordCitation($source->primaryId(), $target->primaryId(), 2.5);

    $mapped = (new MbsoftCitationGraphMapper())->toGraph($graph);
    $sourceId = $source->primaryId()->toString();
    $targetId = $target->primaryId()->toString();

    expect($mapped->isDirected())->toBeTrue()
        ->and($mapped->nodes())->toContain($sourceId, $targetId)
        ->and($mapped->hasEdge($sourceId, $targetId))->toBeTrue()
        ->and($mapped->edgeAttrs($sourceId, $targetId)['weight'])->toBe(2.5)
        ->and($mapped->edgeAttrs($sourceId, $targetId)['type'])->toBe('citation')
        ->and($mapped->nodeAttrs($sourceId)['title'])->toBe('Source Work')
        ->and($mapped->nodeAttrs($sourceId)['source_provider'])->toBe('openalex')
        ->and($mapped->nodeAttrs($sourceId)['external'])->toBeFalse();
});

it('maps non-citation graph types to undirected mbsoft graphs', function (): void {
    $left = citationMapperTestWork('10.1000/left', 'Left Work');
    $right = citationMapperTestWork('10.1000/right', 'Right Work');
    $graph = CitationGraph::create(CitationGraphType::CO_CITATION, 'project-1');

    $graph->addWork($left);
    $graph->addWork($right);
    $graph->recordCitation($left->primaryId(), $right->primaryId(), 3.0);

    $mapped = (new MbsoftCitationGraphMapper())->toGraph($graph);
    $leftId = $left->primaryId()->toString();
    $rightId = $right->primaryId()->toString();

    expect($mapped->isDirected())->toBeFalse()
        ->and($mapped->hasEdge($leftId, $rightId))->toBeTrue()
        ->and($mapped->hasEdge($rightId, $leftId))->toBeTrue()
        ->and($mapped->edgeAttrs($leftId, $rightId)['weight'])->toBe(3.0);
});

it('keeps externally referenced works as explicit external nodes', function (): void {
    $source = citationMapperTestWork('10.1000/source', 'Source Work');
    $externalId = new WorkId(WorkIdNamespace::DOI, '10.1000/external');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');

    $graph->addWork($source);
    $graph->recordCitation($source->primaryId(), $externalId);

    $mapped = (new MbsoftCitationGraphMapper())->toGraph($graph);

    expect($mapped->hasNode($externalId->toString()))->toBeTrue()
        ->and($mapped->nodeAttrs($externalId->toString())['external'])->toBeTrue()
        ->and($mapped->hasEdge($source->primaryId()->toString(), $externalId->toString()))->toBeTrue();
});
