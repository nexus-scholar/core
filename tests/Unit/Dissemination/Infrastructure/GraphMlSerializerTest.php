<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\Serializer\GraphMlSerializer;
use Nexus\Shared\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('serializes_a_corpus_to_graphml_nodes', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);

    $serializer = new GraphMlSerializer;
    $output = $serializer->serialize($corpus);

    expect($output)->toContain('<graphml');
    expect($output)->toContain('node id="doi:10.1234/test"');
    expect($output)->toContain('<data key="label">A Test Work</data>');
});
