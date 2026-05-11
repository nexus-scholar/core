<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\Serializer\GexfSerializer;
use Nexus\Search\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('serializes_a_corpus_to_gexf_nodes', function (): void {
	$work = PersistenceFactory::makeWork();
	$corpus = CorpusSlice::fromWorks($work);

	$serializer = new GexfSerializer();
	$output = $serializer->serialize($corpus);

	expect($output)->toContain('<gexf');
	expect($output)->toContain('node id="doi:10.1234/test"');
	expect($output)->toContain('label="A Test Work"');
});
