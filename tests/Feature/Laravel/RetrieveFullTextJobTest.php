<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Application\UseCase\RetrieveFullText;
use Nexus\Dissemination\Domain\Port\DownloadResult;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\FullTextSourcePort;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Laravel\Job\RetrieveFullTextJob;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Tests\Support\PersistenceFactory;

it('is a queueable job that serializes only the full text retrieval payload', function (): void {
    $job = new RetrieveFullTextJob(
        PersistenceFactory::makeWork(
            doi: '10.5555/fulltext-job',
            title: 'Queueable Full Text Retrieval',
            year: 2025,
        ),
        'custom-pdfs',
    );

    $restored = unserialize(serialize($job));

    expect($restored)->toBeInstanceOf(RetrieveFullTextJob::class)
        ->and($restored)->toBeInstanceOf(ShouldQueue::class)
        ->and($restored->work->primaryId()?->toString())->toBe('doi:10.5555/fulltext-job')
        ->and($restored->work->title())->toBe('Queueable Full Text Retrieval')
        ->and($restored->work->year())->toBe(2025)
        ->and($restored->destinationFolder)->toBe('custom-pdfs');
});

it('resolves the full text handler from the container when handling the job', function (): void {
    Event::fake([NexusJobStarted::class, NexusJobCompleted::class, NexusJobFailed::class]);

    $received = (object) [
        'work' => null,
        'storedPath' => null,
        'saved' => [],
    ];

    app()->instance(FullTextSourceCollection::class, new FullTextSourceCollection(
        new class($received) implements FullTextSourcePort {
            public function __construct(private readonly object $received) {}

            public function resolve(ScholarlyWork $work): ?string
            {
                $this->received->work = $work;

                return 'https://example.org/full-text.pdf';
            }

            public function alias(): string
            {
                return 'example';
            }

            public function supports(ScholarlyWork $work): bool
            {
                return true;
            }
        },
    ));

    app()->instance(FileStoragePort::class, new class($received) implements FileStoragePort {
        public function __construct(private readonly object $received) {}

        public function store(string $filename, string $content): string
        {
            $this->received->storedPath = $filename;

            return $filename;
        }

        public function get(string $path): string
        {
            return '%PDF-1.4 test-content';
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

    app()->instance(PdfDownloaderPort::class, new class implements PdfDownloaderPort {
        public function download(string $url): DownloadResult
        {
            return new DownloadResult('%PDF-1.4 test-content', 200);
        }
    });

    app()->instance(PdfFetchRepositoryPort::class, new class($received) implements PdfFetchRepositoryPort {
        public function __construct(private readonly object $received) {}

        public function save(WorkId $workId, string $sourceUrl, FullTextResult $result, int $durationMs): void
        {
            $this->received->saved[] = compact('workId', 'sourceUrl', 'result', 'durationMs');
        }

        public function findSuccessfulPath(WorkId $workId): ?string
        {
            return null;
        }

        public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
        {
            return false;
        }
    });

    $job = new RetrieveFullTextJob(
        PersistenceFactory::makeWork(doi: '10.5555/container-job', title: 'Container Resolved Retrieval'),
        'queued-pdfs',
    );

    $result = app()->call([$job, 'handle']);

    expect($result->status->value)->toBe('success')
        ->and($received->work)->toBeInstanceOf(ScholarlyWork::class)
        ->and($received->work->title())->toBe('Container Resolved Retrieval')
        ->and($received->storedPath)->toContain('queued-pdfs/')
        ->and($received->storedPath)->toContain('doi:10.5555/container-job_example.pdf')
        ->and($received->saved)->toHaveCount(1)
        ->and($received->saved[0]['sourceUrl'])->toBe('https://example.org/full-text.pdf')
        ->and($received->saved[0]['result'])->toBe($result);

    Event::assertDispatched(
        NexusJobStarted::class,
        fn (NexusJobStarted $event): bool => $event->jobName === 'retrieve_full_text'
            && $event->context['work_id'] === 'doi:10.5555/container-job'
            && $event->context['destination_folder'] === 'queued-pdfs'
    );
    Event::assertDispatched(
        NexusJobCompleted::class,
        fn (NexusJobCompleted $event): bool => $event->jobName === 'retrieve_full_text'
            && $event->summary['status'] === 'success'
            && $event->summary['source_alias'] === 'example'
            && str_contains((string) $event->summary['file_path'], 'queued-pdfs/')
    );
    Event::assertNotDispatched(NexusJobFailed::class);
});
