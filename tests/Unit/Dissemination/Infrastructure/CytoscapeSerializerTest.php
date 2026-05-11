<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\Serializer\CytoscapeSerializer;
use Nexus\Search\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('serializes_a_corpus_to_cytoscape_json', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);

    $serializer = new CytoscapeSerializer();
    $output = $serializer->serialize($corpus);

    $data = json_decode($output, true);

    expect($data['elements']['nodes'][0]['data']['id'])->toBe('doi:10.1234/test');
    expect($data['elements']['nodes'][0]['data']['label'])->toBe('A Test Work');
    expect($data['elements']['edges'])->toBe([]);
});

