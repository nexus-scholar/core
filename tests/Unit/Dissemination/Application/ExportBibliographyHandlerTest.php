<?php

declare(strict_types=1);

use Nexus\Dissemination\Application\UseCase\ExportBibliography;
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\Port\BibliographySerializerPort;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use Nexus\Search\Domain\CorpusSlice;
use Tests\Support\PersistenceFactory;

it('stores_serialized_bibliography_with_matching_serializer', function (): void {
	$work = PersistenceFactory::makeWork();
	$corpus = CorpusSlice::fromWorks($work);

	$serializer = new class implements BibliographySerializerPort {
		public function serialize(CorpusSlice $corpus): string
		{
			return 'serialized-content';
		}

		public function supports(BibliographyFormat $format): bool
		{
			return $format === BibliographyFormat::CSV;
		}
	};

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

it('throws_when_no_serializer_supports_format', function (): void {
	$handler = new ExportBibliographyHandler(new SerializerCollection(), new class implements FileStoragePort {
		public function store(string $filename, string $content): string
		{
			return $filename;
		}

		public function get(string $path): string
		{
			return '';
		}

		public function delete(string $path): void
		{
		}

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
