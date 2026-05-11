<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\Serializer\JsonSerializer;
use Nexus\Search\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('serializes_a_corpus_to_json', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);

    $serializer = new JsonSerializer();
    $output = $serializer->serialize($corpus);

    $data = json_decode($output, true);

    expect($data)->toHaveCount(1);
    expect($data[0]['title'])->toBe('A Test Work');
    expect($data[0]['ids'][0]['ns'])->toBe('doi');
    expect($data[0]['ids'][0]['val'])->toBe('10.1234/test');
});

