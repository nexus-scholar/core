<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Infrastructure\Graph\MbsoftNetworkMetricsCalculator;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

function citationMetricsTestWork(string $doi, string $title): ScholarlyWork
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

it('computes citation network metrics through graph packages', function (): void {
    $workA = citationMetricsTestWork('10.1000/a', 'A');
    $workB = citationMetricsTestWork('10.1000/b', 'B');
    $workC = citationMetricsTestWork('10.1000/c', 'C');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');

    foreach ([$workA, $workB, $workC] as $work) {
        $graph->addWork($work);
    }

    $graph->recordCitation($workA->primaryId(), $workB->primaryId());
    $graph->recordCitation($workC->primaryId(), $workB->primaryId());

    $metrics = (new MbsoftNetworkMetricsCalculator())->compute($graph);
    $a = $workA->primaryId();
    $b = $workB->primaryId();
    $c = $workC->primaryId();

    expect($metrics->nodeCount)->toBe(3)
        ->and($metrics->edgeCount)->toBe(2)
        ->and($metrics->density)->toBe(1 / 3)
        ->and($metrics->inDegreeOf($b))->toBe(2.0)
        ->and($metrics->outDegreeOf($a))->toBe(1.0)
        ->and($metrics->totalDegreeOf($b))->toBe(2.0)
        ->and($metrics->coreNumberOf($a))->toBe(1)
        ->and($metrics->pageRankOf($b))->toBeGreaterThan($metrics->pageRankOf($a))
        ->and($metrics->pageRankOf($b))->toBeGreaterThan($metrics->pageRankOf($c))
        ->and($metrics->weakComponents)->toHaveCount(1)
        ->and($metrics->stronglyConnectedComponents)->toHaveCount(3);
});

it('finds directed shortest citation paths through graph packages', function (): void {
    $workA = citationMetricsTestWork('10.1000/a', 'A');
    $workB = citationMetricsTestWork('10.1000/b', 'B');
    $workC = citationMetricsTestWork('10.1000/c', 'C');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');

    foreach ([$workA, $workB, $workC] as $work) {
        $graph->addWork($work);
    }

    $graph->recordCitation($workA->primaryId(), $workB->primaryId());
    $graph->recordCitation($workB->primaryId(), $workC->primaryId());

    $calculator = new MbsoftNetworkMetricsCalculator();
    $path = $calculator->shortestPath($graph, $workA->primaryId(), $workC->primaryId());

    expect($path)->not->toBeNull()
        ->and($path->nodes)->toBe([
            $workA->primaryId()->toString(),
            $workB->primaryId()->toString(),
            $workC->primaryId()->toString(),
        ])
        ->and($path->cost)->toBe(2.0)
        ->and($calculator->shortestPath($graph, $workC->primaryId(), $workA->primaryId()))->toBeNull();
});
