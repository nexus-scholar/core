<?php

declare(strict_types=1);

use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Application\UseCase\RetrieveFullText;
use Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler;
use Nexus\Dissemination\Domain\Port\DownloadResult;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\FullTextSourcePort;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Tests\Support\PersistenceFactory;

it('retrieves_full_text_and_persists_audit_entry', function (): void {
    $work = PersistenceFactory::makeWork();

    $source = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/test.pdf';
        }

        public function alias(): string
        {
            return 'example';
        }

        public function supports(ScholarlyWork $work): bool
        {
            return true;
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

    $downloader = new class implements PdfDownloaderPort {
        public function download(string $url): DownloadResult
        {
            return new DownloadResult('%PDF-1.4 test-content', 200);
        }
    };

    $repository = new class implements PdfFetchRepositoryPort {
        public array $saved = [];

        public function save(WorkId $workId, string $sourceUrl, FullTextResult $result, int $durationMs): void
        {
            $this->saved[] = compact('workId', 'sourceUrl', 'result', 'durationMs');
        }

        public function findSuccessfulPath(WorkId $workId): ?string
        {
            return null;
        }
    };

    $handler = new RetrieveFullTextHandler(
        new FullTextSourceCollection($source),
        $storage,
        $downloader,
        $repository
    );

    $command = new RetrieveFullText($work, 'pdfs');
    $result = $handler->handle($command);

    expect($result->status->value)->toBe('success');
    expect($result->filePath)->toContain('pdfs/');
    expect($storage->exists($result->filePath))->toBeTrue();
    expect($repository->saved)->toHaveCount(1);
});

