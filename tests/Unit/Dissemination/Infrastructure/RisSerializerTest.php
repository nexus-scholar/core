<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\Serializer\RisSerializer;
use Nexus\Search\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('serializes_a_work_to_ris', function (): void {
	$work = PersistenceFactory::makeWork();
	$corpus = CorpusSlice::fromWorks($work);

	$serializer = new RisSerializer();
	$output = $serializer->serialize($corpus);

	expect($output)->toContain('TY  - JOUR');
	expect($output)->toContain('TI  - A Test Work');
	expect($output)->toContain('AU  - John Doe');
	expect($output)->toContain('PY  - 2024');
	expect($output)->toContain('JO  - Test Journal');
	expect($output)->toContain('DO  - 10.1234/test');
	expect($output)->toContain('ER  -');
});
