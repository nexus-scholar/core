<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\Serializer\BibTexSerializer;
use Nexus\Search\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('serializes_a_work_to_bibtex', function (): void {
	$work = PersistenceFactory::makeWork();
	$corpus = CorpusSlice::fromWorks($work);

	$serializer = new BibTexSerializer();
	$output = $serializer->serialize($corpus);

	expect($output)->toContain('@article{doi:10.1234/test');
	expect($output)->toContain('title = {A Test Work}');
	expect($output)->toContain('author = {John Doe}');
	expect($output)->toContain('year = {2024}');
	expect($output)->toContain('journal = {Test Journal}');
	expect($output)->toContain('doi = {doi:10.1234/test}');
});
