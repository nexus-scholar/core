<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\Serializer\CsvSerializer;
use Nexus\Shared\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('serializes_a_corpus_to_csv', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);

    $serializer = new CsvSerializer;
    $output = $serializer->serialize($corpus);

    $lines = preg_split("/\r\n|\n|\r/", trim($output));
    expect($lines)->not->toBeEmpty();

    $header = str_getcsv($lines[0]);
    expect($header)->toBe([
        'ID',
        'Title',
        'Authors',
        'Year',
        'Venue',
        'DOI',
        'Source',
        'Cited By',
    ]);

    $row = str_getcsv($lines[1]);
    expect($row[1])->toBe('A Test Work');
    expect($row[2])->toBe('John Doe');
    expect($row[3])->toBe('2024');
    expect($row[4])->toBe('Test Journal');
    expect($row[5])->toBe('doi:10.1234/test');
});
