<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\Serializer\JsonlSerializer;
use Nexus\Search\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('serializes_a_corpus_to_json_lines', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);

    $serializer = new JsonlSerializer();
    $output = $serializer->serialize($corpus);

    $lines = preg_split("/\r\n|\n|\r/", trim($output));
    expect($lines)->toHaveCount(1);

    $data = json_decode($lines[0], true);
    expect($data['title'])->toBe('A Test Work');
    expect($data['ids'][0]['ns'])->toBe('doi');
    expect($data['ids'][0]['val'])->toBe('10.1234/test');
});

