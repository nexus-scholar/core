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

it('returns an existing successful fetch without downloading again', function (): void {
    $calls = (object) [
        'supports' => 0,
        'resolve' => 0,
        'downloads' => 0,
        'stores' => 0,
        'saves' => 0,
    ];

    $source = new class($calls) implements FullTextSourcePort {
        public function __construct(private readonly object $calls) {}

        public function resolve(ScholarlyWork $work): ?string
        {
            $this->calls->resolve++;

            return 'https://example.org/test.pdf';
        }

        public function alias(): string
        {
            return 'example';
        }

        public function supports(ScholarlyWork $work): bool
        {
            $this->calls->supports++;

            return true;
        }
    };

    $storage = new class($calls) implements FileStoragePort {
        public function __construct(private readonly object $calls) {}

        public function store(string $filename, string $content): string
        {
            $this->calls->stores++;

            return $filename;
        }

        public function get(string $path): string
        {
            return '';
        }

        public function delete(string $path): void {}

        public function exists(string $path): bool
        {
            return $path === 'pdfs/cached.pdf';
        }

        public function url(string $path): ?string
        {
            return null;
        }
    };

    $downloader = new class($calls) implements PdfDownloaderPort {
        public function __construct(private readonly object $calls) {}

        public function download(string $url): DownloadResult
        {
            $this->calls->downloads++;

            return new DownloadResult('%PDF-1.4 test-content', 200, 'application/pdf');
        }
    };

    $repository = new class($calls) implements PdfFetchRepositoryPort {
        public function __construct(private readonly object $calls) {}

        public function save(WorkId $workId, string $sourceUrl, FullTextResult $result, int $durationMs): void
        {
            $this->calls->saves++;
        }

        public function findSuccessfulPath(WorkId $workId): ?string
        {
            return 'pdfs/cached.pdf';
        }
    };

    $handler = new RetrieveFullTextHandler(
        new FullTextSourceCollection($source),
        $storage,
        $downloader,
        $repository
    );

    $result = $handler->handle(new RetrieveFullText(PersistenceFactory::makeWork(), 'pdfs'));

    expect($result->status->value)->toBe('success')
        ->and($result->filePath)->toBe('pdfs/cached.pdf')
        ->and($result->sourceAlias)->toBe('cache')
        ->and($calls->supports)->toBe(0)
        ->and($calls->resolve)->toBe(0)
        ->and($calls->downloads)->toBe(0)
        ->and($calls->stores)->toBe(0)
        ->and($calls->saves)->toBe(0);
});

it('rejects non-pdf downloads and continues to the next source', function (): void {
    $sourceA = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/not-pdf';
        }

        public function alias(): string
        {
            return 'bad';
        }

        public function supports(ScholarlyWork $work): bool
        {
            return true;
        }
    };

    $sourceB = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/good.pdf';
        }

        public function alias(): string
        {
            return 'good';
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
            return null;
        }
    };

    $downloader = new class implements PdfDownloaderPort {
        public function download(string $url): DownloadResult
        {
            if ($url === 'https://example.org/not-pdf') {
                return new DownloadResult('<html>not a pdf</html>', 200, 'text/html');
            }

            return new DownloadResult('%PDF-1.7 test-content', 200, 'application/pdf; charset=binary');
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
        new FullTextSourceCollection($sourceA, $sourceB),
        $storage,
        $downloader,
        $repository
    );

    $result = $handler->handle(new RetrieveFullText(PersistenceFactory::makeWork(), 'pdfs'));

    expect($result->status->value)->toBe('success')
        ->and($result->sourceAlias)->toBe('good')
        ->and($storage->stored)->toHaveCount(1)
        ->and($repository->saved)->toHaveCount(2)
        ->and($repository->saved[0]['sourceUrl'])->toBe('https://example.org/not-pdf')
        ->and($repository->saved[0]['result']->status->value)->toBe('failure')
        ->and($repository->saved[0]['result']->errorMessage)->toContain('missing %PDF signature')
        ->and($repository->saved[1]['sourceUrl'])->toBe('https://example.org/good.pdf')
        ->and($repository->saved[1]['result'])->toBe($result);
});
