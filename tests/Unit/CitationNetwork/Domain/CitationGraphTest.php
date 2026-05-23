<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Domain\Exception\WorkNotInGraph;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

function citationGraphDomainTestWork(WorkId $id, string $title): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([$id]),
        title: $title,
        sourceProvider: 'test',
    );
}

it('keys works by full namespace-qualified work id', function (): void {
    $doiWork = citationGraphDomainTestWork(new WorkId(WorkIdNamespace::DOI, 'shared-value'), 'DOI Work');
    $s2Work = citationGraphDomainTestWork(new WorkId(WorkIdNamespace::S2, 'shared-value'), 'S2 Work');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');

    $graph->addWork($doiWork);
    $graph->addWork($s2Work);

    expect($graph->nodeCount())->toBe(2)
        ->and($graph->hasWork($doiWork->primaryId()))->toBeTrue()
        ->and($graph->hasWork($s2Work->primaryId()))->toBeTrue()
        ->and($graph->workByIdString($doiWork->primaryId()->toString()))->toBe($doiWork)
        ->and($graph->workByIdString($s2Work->primaryId()->toString()))->toBe($s2Work);
});

it('throws when recording an edge from a work that is not in the graph', function (): void {
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');
    $missing = new WorkId(WorkIdNamespace::DOI, '10.1000/missing');
    $target = new WorkId(WorkIdNamespace::DOI, '10.1000/target');

    expect(fn () => $graph->recordCitation($missing, $target))->toThrow(WorkNotInGraph::class);
});

it('allows citations to external works that are not graph nodes', function (): void {
    $source = citationGraphDomainTestWork(new WorkId(WorkIdNamespace::DOI, '10.1000/source'), 'Source');
    $externalTarget = new WorkId(WorkIdNamespace::DOI, '10.1000/external-target');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');

    $graph->addWork($source);
    $graph->recordCitation($source->primaryId(), $externalTarget);

    expect($graph->edgeCount())->toBe(1)
        ->and($graph->allEdges()[0]->cited->equals($externalTarget))->toBeTrue()
        ->and($graph->hasWork($externalTarget))->toBeFalse();
});

it('deduplicates edges by namespace and normalized value', function (): void {
    $source = citationGraphDomainTestWork(new WorkId(WorkIdNamespace::DOI, '10.1000/source'), 'Source');
    $target = citationGraphDomainTestWork(new WorkId(WorkIdNamespace::DOI, '10.1000/target'), 'Target');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');

    $graph->addWork($source);
    $graph->addWork($target);
    $graph->recordCitation($source->primaryId(), $target->primaryId());
    $graph->recordCitation(new WorkId(WorkIdNamespace::DOI, 'https://doi.org/10.1000/source'), $target->primaryId());

    expect($graph->edgeCount())->toBe(1);
});
