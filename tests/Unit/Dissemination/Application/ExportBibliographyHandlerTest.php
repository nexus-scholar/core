<?php

declare(strict_types=1);

use Nexus\Dissemination\Application\UseCase\ExportBibliography;
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\ExportType;
use Nexus\Dissemination\Domain\Port\BibliographySerializerPort;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use Nexus\Shared\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('stores_serialized_bibliography_with_matching_serializer', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);

    $serializer = new class implements BibliographySerializerPort
    {
        public function serialize(CorpusSlice $corpus): string
        {
            return 'serialized-content';
        }

        public function supports(BibliographyFormat $format): bool
        {
            return $format === BibliographyFormat::CSV;
        }
    };

    $storage = new class implements FileStoragePort
    {
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
            return $this->exists($path) ? 'memory://'.$path : null;
        }
    };

    $handler = new ExportBibliographyHandler(
        new SerializerCollection($serializer),
        $storage
    );

    $command = new ExportBibliography(
        corpus: $corpus,
        format: BibliographyFormat::CSV,
        filename: 'exports/test.csv'
    );

    $path = $handler->handle($command);

    expect($path)->toBe('exports/test.csv');
    expect($storage->get('exports/test.csv'))->toBe('serialized-content');
});

it('records bibliography export history when a recorder is provided', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);

    $history = new class implements ExportHistoryPort
    {
        public ?ExportHistoryRecord $record = null;

        public function record(ExportHistoryRecord $record): void
        {
            $this->record = $record;
        }
    };

    $handler = new ExportBibliographyHandler(
        new SerializerCollection(new class implements BibliographySerializerPort
        {
            public function serialize(CorpusSlice $corpus): string
            {
                return 'serialized-content';
            }

            public function supports(BibliographyFormat $format): bool
            {
                return $format === BibliographyFormat::CSV;
            }
        }),
        exportBibliographyHandlerTestStorage(),
        $history,
    );

    $handler->handle(new ExportBibliography(
        corpus: $corpus,
        format: BibliographyFormat::CSV,
        filename: 'exports/test.csv',
        projectId: 'project-1',
        requestedBy: 'user-1',
        metadata: ['source' => 'unit'],
    ));

    expect($history->record)->toBeInstanceOf(ExportHistoryRecord::class)
        ->and($history->record->type)->toBe(ExportType::BIBLIOGRAPHY)
        ->and($history->record->format)->toBe('csv')
        ->and($history->record->mimeType)->toBe('text/csv')
        ->and($history->record->sizeBytes)->toBe(strlen('serialized-content'))
        ->and($history->record->projectId)->toBe('project-1')
        ->and($history->record->corpusSliceId)->toBe($corpus->id->value)
        ->and($history->record->requestedBy)->toBe('user-1')
        ->and($history->record->metadata)->toBe(['source' => 'unit']);
});

it('rejects bibliography exports whose filename extension does not match the format', function (): void {
    $handler = new ExportBibliographyHandler(
        new SerializerCollection,
        exportBibliographyHandlerTestStorage(),
    );

    expect(fn () => $handler->handle(new ExportBibliography(
        corpus: CorpusSlice::empty(),
        format: BibliographyFormat::BIBTEX,
        filename: 'exports/test.csv',
    )))->toThrow(InvalidArgumentException::class, 'Export filename extension must match format bibtex (.bib): exports/test.csv');
});

it('throws_when_no_serializer_supports_format', function (): void {
    $handler = new ExportBibliographyHandler(new SerializerCollection, new class implements FileStoragePort
    {
        public function store(string $filename, string $content): string
        {
            return $filename;
        }

        public function get(string $path): string
        {
            return '';
        }

        public function delete(string $path): void {}

        public function exists(string $path): bool
        {
            return false;
        }

        public function url(string $path): ?string
        {
            return null;
        }
    });

    $command = new ExportBibliography(
        corpus: CorpusSlice::empty(),
        format: BibliographyFormat::BIBTEX,
        filename: 'exports/test.bib'
    );

    expect(fn () => $handler->handle($command))
        ->toThrow(RuntimeException::class, 'No serializer found for format: bibtex');
});

function exportBibliographyHandlerTestStorage(): FileStoragePort
{
    return new class implements FileStoragePort
    {
        /** @var array<string, string> */
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
            return $this->exists($path) ? 'memory://'.$path : null;
        }
    };
}
