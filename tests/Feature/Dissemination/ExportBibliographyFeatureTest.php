<?php

declare(strict_types=1);

use Nexus\Dissemination\Application\UseCase\ExportBibliography;
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use Nexus\Dissemination\Infrastructure\Serializer\CsvSerializer;
use Nexus\Search\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('exports_bibliography_to_storage', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);

    $storage = new class implements FileStoragePort {
        public array $stored = [];

        public function store(string $filename, string $content): string
        {
            $this->stored[$filename] = $content;

            return $filename;
        }

        public function get(string $path): string
        {
            return $this->stored[$path] ?? '';
        }

        public function delete(string $path): void
        {
            unset($this->stored[$path]);
        }

        public function exists(string $path): bool
        {
            return array_key_exists($path, $this->stored);
        }

        public function url(string $path): ?string
        {
            return $this->exists($path) ? 'memory://' . $path : null;
        }
    };

    $handler = new ExportBibliographyHandler(
        new SerializerCollection(new CsvSerializer()),
        $storage
    );

    $command = new ExportBibliography(
        corpus: $corpus,
        format: BibliographyFormat::CSV,
        filename: 'exports/works.csv'
    );

    $path = $handler->handle($command);

    expect($path)->toBe('exports/works.csv');
    expect($storage->exists('exports/works.csv'))->toBeTrue();
    expect($storage->get('exports/works.csv'))->toContain('A Test Work');
});

