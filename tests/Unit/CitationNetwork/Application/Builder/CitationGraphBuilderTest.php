<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

function citationBuilderTestWork(string $doi, string $title, array $extraIds = []): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::DOI, $doi),
            ...$extraIds,
        ]),
        title: $title,
        sourceProvider: 'openalex',
    );
}

it('builds direct citation graphs from provider reference ids', function (): void {
    $workA = citationBuilderTestWork('10.1000/a', 'A');
    $workB = citationBuilderTestWork('10.1000/b', 'B', [new WorkId(WorkIdNamespace::OPENALEX, 'W-B')]);
    $workC = citationBuilderTestWork('10.1000/c', 'C');

    $graph = (new CitationGraphBuilder)->buildDirectCitationGraph(
        'project-1',
        [$workA, $workB, $workC],
        [
            $workA->primaryId()->toString() => ['openalex:w-b', 'doi:10.1000/unknown'],
            $workC->primaryId()->toString() => ['doi:10.1000/b'],
        ],
    );

    expect($graph->type)->toBe(CitationGraphType::CITATION)
        ->and($graph->nodeCount())->toBe(3)
        ->and($graph->edgeCount())->toBe(2);

    $edges = array_map(
        fn ($edge): string => $edge->citing->toString().'->'.$edge->cited->toString(),
        $graph->allEdges(),
    );

    expect($edges)->toContain(
        $workA->primaryId()->toString().'->'.$workB->primaryId()->toString(),
        $workC->primaryId()->toString().'->'.$workB->primaryId()->toString(),
    );
});

it('builds co-citation graphs with inverted indexes from reference lists', function (): void {
    $workA = citationBuilderTestWork('10.1000/a', 'A');
    $workB = citationBuilderTestWork('10.1000/b', 'B');
    $workC = citationBuilderTestWork('10.1000/c', 'C');

    $graph = (new CitationGraphBuilder)->buildCoCitationGraph(
        'project-1',
        [$workA, $workB, $workC],
        [
            's2:citing-one' => ['doi:10.1000/a', 'doi:10.1000/b'],
            's2:citing-two' => ['doi:10.1000/a', 'doi:10.1000/b', 'doi:10.1000/c'],
        ],
    );

    expect($graph->type)->toBe(CitationGraphType::CO_CITATION)
        ->and($graph->edgeCount())->toBe(3)
        ->and(edgeWeight($graph, $workA, $workB))->toBe(2.0)
        ->and(edgeWeight($graph, $workA, $workC))->toBe(1.0)
        ->and(edgeWeight($graph, $workB, $workC))->toBe(1.0);
});

it('builds co-citation graphs from provider cited-by lists', function (): void {
    $workA = citationBuilderTestWork('10.1000/a', 'A');
    $workB = citationBuilderTestWork('10.1000/b', 'B');
    $workC = citationBuilderTestWork('10.1000/c', 'C');

    $graph = (new CitationGraphBuilder)->buildCoCitationGraph(
        'project-1',
        [$workA, $workB, $workC],
        citingWorkIdsByCitedWorkId: [
            $workA->primaryId()->toString() => ['s2:citing-one', 's2:citing-two'],
            $workB->primaryId()->toString() => ['s2:citing-one'],
            $workC->primaryId()->toString() => ['s2:citing-two'],
        ],
    );

    expect($graph->edgeCount())->toBe(2)
        ->and(edgeWeight($graph, $workA, $workB))->toBe(1.0)
        ->and(edgeWeight($graph, $workA, $workC))->toBe(1.0);
});

it('builds bibliographic coupling graphs with inverted indexes', function (): void {
    $workA = citationBuilderTestWork('10.1000/a', 'A');
    $workB = citationBuilderTestWork('10.1000/b', 'B');
    $workC = citationBuilderTestWork('10.1000/c', 'C');

    $graph = (new CitationGraphBuilder)->buildBibliographicCouplingGraph(
        'project-1',
        [$workA, $workB, $workC],
        [
            $workA->primaryId()->toString() => ['openalex:r1', 'openalex:r2'],
            $workB->primaryId()->toString() => ['openalex:r1', 'openalex:r2', 'openalex:r3'],
            $workC->primaryId()->toString() => ['openalex:r2'],
        ],
    );

    expect($graph->type)->toBe(CitationGraphType::BIBLIOGRAPHIC_COUPLING)
        ->and($graph->edgeCount())->toBe(3)
        ->and(edgeWeight($graph, $workA, $workB))->toBe(2.0)
        ->and(edgeWeight($graph, $workA, $workC))->toBe(1.0)
        ->and(edgeWeight($graph, $workB, $workC))->toBe(1.0);
});

function edgeWeight($graph, ScholarlyWork $left, ScholarlyWork $right): ?float
{
    foreach ($graph->allEdges() as $edge) {
        if ($edge->citing->equals($left->primaryId()) && $edge->cited->equals($right->primaryId())) {
            return $edge->weight;
        }

        if ($edge->citing->equals($right->primaryId()) && $edge->cited->equals($left->primaryId())) {
            return $edge->weight;
        }
    }

    return null;
}
