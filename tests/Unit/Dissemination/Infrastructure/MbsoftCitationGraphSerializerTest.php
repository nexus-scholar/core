<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Dissemination\Infrastructure\Serializer\MbsoftCitationGraphSerializer;
use Tests\Support\PersistenceFactory;

it('exports citation graphs to cytoscape json with weighted edges', function (): void {
    $source = PersistenceFactory::makeWork(doi: '10.5555/source', title: 'Source Work');
    $target = PersistenceFactory::makeWork(doi: '10.5555/target', title: 'Target Work');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');
    $graph->addWork($source);
    $graph->addWork($target);
    $graph->recordCitation($source->primaryId(), $target->primaryId(), 2.5);

    $json = (new MbsoftCitationGraphSerializer())->serialize($graph, NetworkExportFormat::CYTOSCAPE);
    $payload = json_decode($json, true);

    expect($payload['elements']['nodes'])->toHaveCount(2)
        ->and($payload['elements']['edges'])->toHaveCount(1)
        ->and($payload['elements']['edges'][0]['data']['source'])->toBe('doi:10.5555/source')
        ->and($payload['elements']['edges'][0]['data']['target'])->toBe('doi:10.5555/target')
        ->and($payload['elements']['edges'][0]['data']['weight'])->toBe(2.5)
        ->and($payload['elements']['nodes'][0]['data'])->toHaveKey('title');
});

it('exports citation graphs to graphml with graph-core attribute metadata', function (): void {
    $source = PersistenceFactory::makeWork(doi: '10.5555/source', title: 'Source Work');
    $target = PersistenceFactory::makeWork(doi: '10.5555/target', title: 'Target Work');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');
    $graph->addWork($source);
    $graph->addWork($target);
    $graph->recordCitation($source->primaryId(), $target->primaryId(), 2.5);

    $xml = (new MbsoftCitationGraphSerializer())->serialize($graph, NetworkExportFormat::GRAPHML);

    expect($xml)->toContain('<graphml')
        ->and($xml)->toContain('edgedefault="directed"')
        ->and($xml)->toContain('attr.name="title"')
        ->and($xml)->toContain('attr.name="weight"')
        ->and($xml)->toContain('source="doi:10.5555/source"')
        ->and($xml)->toContain('target="doi:10.5555/target"')
        ->and($xml)->toContain('>2.5</data>');
});

it('exports citation graphs to gexf with graph-core edge attributes', function (): void {
    $source = PersistenceFactory::makeWork(doi: '10.5555/source', title: 'Source Work');
    $target = PersistenceFactory::makeWork(doi: '10.5555/target', title: 'Target Work');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');
    $graph->addWork($source);
    $graph->addWork($target);
    $graph->recordCitation($source->primaryId(), $target->primaryId(), 2.5);

    $xml = (new MbsoftCitationGraphSerializer())->serialize($graph, NetworkExportFormat::GEXF);

    expect($xml)->toContain('<gexf')
        ->and($xml)->toContain('defaultedgetype="directed"')
        ->and($xml)->toContain('title="weight"')
        ->and($xml)->toContain('source="doi:10.5555/source"')
        ->and($xml)->toContain('target="doi:10.5555/target"')
        ->and($xml)->toContain('value="2.5"');
});
