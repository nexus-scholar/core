<?php

declare(strict_types=1);

use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Application\UseCase\RetrieveFullText;
use Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler;
use Nexus\Dissemination\Domain\Port\DownloadResult;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\FullTextCandidateSourcePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\FullTextSourceCandidate;
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
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

it('stores pdfs with deterministic portable paths', function (): void {
    $work = PersistenceFactory::makeWork(doi: '10.5555/path/test');

    $source = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/test.pdf';
        }

        public function alias(): string
        {
            return 'bad/source alias';
        }

        public function supports(ScholarlyWork $work): bool
        {
            return true;
        }
    };

    $storage = new class implements FileStoragePort {
        public ?string $storedPath = null;

        public function store(string $filename, string $content): string
        {
            $this->storedPath = $filename;

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
    };

    $downloader = new class implements PdfDownloaderPort {
        public function download(string $url): DownloadResult
        {
            return new DownloadResult('%PDF-1.4 test-content', 200);
        }
    };

    $repository = new class implements PdfFetchRepositoryPort {
        public function save(WorkId $workId, string $sourceUrl, FullTextResult $result, int $durationMs): void {}

        public function findSuccessfulPath(WorkId $workId): ?string
        {
            return null;
        }

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
        }
    };

    $handler = new RetrieveFullTextHandler(
        new FullTextSourceCollection($source),
        $storage,
        $downloader,
        $repository
    );

    $result = $handler->handle(new RetrieveFullText($work, '../pdf reports'));

    expect($result->status->value)->toBe('success')
        ->and($storage->storedPath)->toStartWith('pdf_reports/')
        ->and(substr_count((string) $storage->storedPath, '/'))->toBe(1)
        ->and((string) $storage->storedPath)->not->toContain(':')
        ->and((string) $storage->storedPath)->not->toContain('\\')
        ->and((bool) preg_match(
            '#^pdf_reports/doi_10\.5555_path_test_bad_source_alias_[a-f0-9]{16}\.pdf$#',
            (string) $storage->storedPath,
        ))->toBeTrue();
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
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

it('retries transient download failures before auditing success', function (): void {
    $source = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/retry.pdf';
        }

        public function alias(): string
        {
            return 'retry';
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

        public function delete(string $path): void {}

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
        public int $attempts = 0;

        public function download(string $url): DownloadResult
        {
            $this->attempts++;

            if ($this->attempts === 1) {
                throw new RuntimeException('temporary network failure', 503);
            }

            return new DownloadResult('%PDF-1.7 retry-content', 200, 'application/pdf');
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
        }
    };

    $handler = new RetrieveFullTextHandler(
        new FullTextSourceCollection($source),
        $storage,
        $downloader,
        $repository
    );

    $result = $handler->handle(new RetrieveFullText(
        work: PersistenceFactory::makeWork(),
        destinationFolder: 'pdfs',
        maxDownloadAttempts: 2,
    ));

    expect($result->status->value)->toBe('success')
        ->and($downloader->attempts)->toBe(2)
        ->and($repository->saved)->toHaveCount(1)
        ->and($repository->saved[0]['result']->status->value)->toBe('success')
        ->and($storage->stored)->toHaveCount(1);
});

it('audits failure after exhausting download retries', function (): void {
    $source = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/failing.pdf';
        }

        public function alias(): string
        {
            return 'failing';
        }

        public function supports(ScholarlyWork $work): bool
        {
            return true;
        }
    };

    $storage = new class implements FileStoragePort {
        public function store(string $filename, string $content): string
        {
            throw new RuntimeException('storage should not be called');
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
    };

    $downloader = new class implements PdfDownloaderPort {
        public int $attempts = 0;

        public function download(string $url): DownloadResult
        {
            $this->attempts++;

            throw new RuntimeException('download still failing', 503);
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
        }
    };

    $handler = new RetrieveFullTextHandler(
        new FullTextSourceCollection($source),
        $storage,
        $downloader,
        $repository
    );

    $result = $handler->handle(new RetrieveFullText(
        work: PersistenceFactory::makeWork(),
        destinationFolder: 'pdfs',
        maxDownloadAttempts: 3,
    ));

    expect($result->status->value)->toBe('failure')
        ->and($result->errorMessage)->toBe('download still failing')
        ->and($downloader->attempts)->toBe(3)
        ->and($repository->saved)->toHaveCount(1)
        ->and($repository->saved[0]['sourceUrl'])->toBe('https://example.org/failing.pdf')
        ->and($repository->saved[0]['result'])->toBe($result);
});

it('rejects oversized pdf downloads before storage', function (): void {
    $source = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/oversized.pdf';
        }

        public function alias(): string
        {
            return 'oversized';
        }

        public function supports(ScholarlyWork $work): bool
        {
            return true;
        }
    };

    $storage = new class implements FileStoragePort {
        public int $stores = 0;

        public function store(string $filename, string $content): string
        {
            $this->stores++;

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
    };

    $downloader = new class implements PdfDownloaderPort {
        public function download(string $url): DownloadResult
        {
            return new DownloadResult('%PDF-1.7 oversized-content', 200, 'application/pdf');
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
        }
    };

    $handler = new RetrieveFullTextHandler(
        new FullTextSourceCollection($source),
        $storage,
        $downloader,
        $repository
    );

    $result = $handler->handle(new RetrieveFullText(
        work: PersistenceFactory::makeWork(),
        destinationFolder: 'pdfs',
        maxBytes: 10,
    ));

    expect($result->status->value)->toBe('failure')
        ->and($result->errorMessage)->toContain('exceeds size limit')
        ->and($storage->stores)->toBe(0)
        ->and($repository->saved)->toHaveCount(1)
        ->and($repository->saved[0]['result'])->toBe($result);
});

it('skips sources with recent failed attempts during cooldown', function (): void {
    $sourceA = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/recent-failure.pdf';
        }

        public function alias(): string
        {
            return 'cooldown';
        }

        public function supports(ScholarlyWork $work): bool
        {
            return true;
        }
    };

    $sourceB = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/available.pdf';
        }

        public function alias(): string
        {
            return 'available';
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

        public function delete(string $path): void {}

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
        /** @var list<string> */
        public array $urls = [];

        public function download(string $url): DownloadResult
        {
            $this->urls[] = $url;

            return new DownloadResult('%PDF-1.7 available-content', 200, 'application/pdf');
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return $sourceUrl === 'https://example.org/recent-failure.pdf';
        }
    };

    $handler = new RetrieveFullTextHandler(
        new FullTextSourceCollection($sourceA, $sourceB),
        $storage,
        $downloader,
        $repository
    );

    $result = $handler->handle(new RetrieveFullText(
        work: PersistenceFactory::makeWork(),
        destinationFolder: 'pdfs',
        failedAttemptCooldownSeconds: 3600,
    ));

    expect($result->status->value)->toBe('success')
        ->and($result->sourceAlias)->toBe('available')
        ->and($downloader->urls)->toBe(['https://example.org/available.pdf'])
        ->and($repository->saved)->toHaveCount(1)
        ->and($repository->saved[0]['sourceUrl'])->toBe('https://example.org/available.pdf');
});

it('carries full text source candidate metadata into audit rows', function (): void {
    $source = new class implements FullTextCandidateSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return $this->resolveCandidate($work)?->url;
        }

        public function resolveCandidate(ScholarlyWork $work): ?FullTextSourceCandidate
        {
            return FullTextSourceCandidate::pdf('https://example.org/open.pdf', [
                'source' => 'unpaywall',
                'license' => 'cc-by',
            ]);
        }

        public function alias(): string
        {
            return 'unpaywall';
        }

        public function supports(ScholarlyWork $work): bool
        {
            return true;
        }
    };

    $storage = new class implements FileStoragePort {
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
    };

    $downloader = new class implements PdfDownloaderPort {
        public function download(string $url): DownloadResult
        {
            return new DownloadResult('%PDF-1.7 metadata-content', 200, 'application/pdf');
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
        }
    };

    $result = (new RetrieveFullTextHandler(
        new FullTextSourceCollection($source),
        $storage,
        $downloader,
        $repository,
    ))->handle(new RetrieveFullText(PersistenceFactory::makeWork(), 'pdfs'));

    expect($result->status->value)->toBe('success')
        ->and($repository->saved[0]['result']->metadata)->toMatchArray([
            'artifact_type' => 'pdf',
            'source' => 'unpaywall',
            'license' => 'cc-by',
        ]);
});

it('stores XML full text candidates and extracts a text sidecar', function (): void {
    $source = new class implements FullTextCandidateSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return $this->resolveCandidate($work)?->url;
        }

        public function resolveCandidate(ScholarlyWork $work): ?FullTextSourceCandidate
        {
            return FullTextSourceCandidate::xml('https://pmc.ncbi.nlm.nih.gov/api/oai/v1/mh/?verb=GetRecord', [
                'source' => 'pmc',
                'pmcid' => 'PMC12124693',
            ]);
        }

        public function alias(): string
        {
            return 'pmc';
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

        public function delete(string $path): void {}

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
        public int $downloads = 0;

        public function download(string $url): DownloadResult
        {
            $this->downloads++;

            return new DownloadResult(
                '<article><front><article-title>Test XML Paper</article-title></front><body><p>Useful full text.</p></body></article>',
                200,
                'application/xml',
            );
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
        }
    };

    $result = (new RetrieveFullTextHandler(
        new FullTextSourceCollection($source),
        $storage,
        $downloader,
        $repository,
    ))->handle(new RetrieveFullText(PersistenceFactory::makeWork(), 'pdfs'));

    expect($result->status->value)->toBe('success')
        ->and($result->sourceAlias)->toBe('pmc')
        ->and($result->filePath)->toEndWith('.xml')
        ->and($result->metadata)->toMatchArray([
            'artifact_type' => 'xml',
            'source' => 'pmc',
            'pmcid' => 'PMC12124693',
            'text_extraction' => 'xml_text_content',
        ])
        ->and($result->metadata['text_file_path'])->toEndWith('.txt')
        ->and($storage->get($result->filePath))->toContain('Useful full text.')
        ->and($storage->get($result->metadata['text_file_path']))->toContain('Test XML Paper Useful full text.')
        ->and($downloader->downloads)->toBe(1)
        ->and($repository->saved)->toHaveCount(1)
        ->and($repository->saved[0]['result']->status->value)->toBe('success');
});

it('rejects invalid XML artifacts and continues to the next source', function (): void {
    $xmlSource = new class implements FullTextCandidateSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return $this->resolveCandidate($work)?->url;
        }

        public function resolveCandidate(ScholarlyWork $work): ?FullTextSourceCandidate
        {
            return FullTextSourceCandidate::xml('https://pmc.example.invalid/bad.xml', ['source' => 'pmc']);
        }

        public function alias(): string
        {
            return 'pmc';
        }

        public function supports(ScholarlyWork $work): bool
        {
            return true;
        }
    };

    $pdfSource = new class implements FullTextSourcePort {
        public function resolve(ScholarlyWork $work): ?string
        {
            return 'https://example.org/good.pdf';
        }

        public function alias(): string
        {
            return 'direct';
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

        public function delete(string $path): void {}

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
            if ($url === 'https://pmc.example.invalid/bad.xml') {
                return new DownloadResult('<article><body>broken', 200, 'application/xml');
            }

            return new DownloadResult('%PDF-1.7 fallback-content', 200, 'application/pdf');
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

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
        }
    };

    $result = (new RetrieveFullTextHandler(
        new FullTextSourceCollection($xmlSource, $pdfSource),
        $storage,
        $downloader,
        $repository,
    ))->handle(new RetrieveFullText(PersistenceFactory::makeWork(), 'pdfs'));

    expect($result->status->value)->toBe('success')
        ->and($result->sourceAlias)->toBe('direct')
        ->and($result->filePath)->toEndWith('.pdf')
        ->and($repository->saved)->toHaveCount(2)
        ->and($repository->saved[0]['result']->status->value)->toBe('failure')
        ->and($repository->saved[0]['result']->errorMessage)->toContain('not valid XML')
        ->and($repository->saved[1]['result'])->toBe($result);
});
