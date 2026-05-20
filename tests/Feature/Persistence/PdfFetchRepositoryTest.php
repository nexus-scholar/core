<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Tests\Support\PersistenceFactory;

beforeEach(function (): void {
    $this->repository = app(PdfFetchRepositoryPort::class);
    $this->workRepository = app(WorkRepositoryPort::class);
});

it('stores pdf fetch audit rows against the internal work id', function (): void {
    $work = PersistenceFactory::makeWork();
    $this->workRepository->save($work);

    $internalWorkId = DB::table('work_external_ids')
        ->where('namespace', $work->primaryId()->namespace->value)
        ->where('value', $work->primaryId()->value)
        ->value('work_id');

    $this->repository->save(
        $work->primaryId(),
        'https://example.org/paper.pdf',
        FullTextResult::success('pdfs/paper.pdf', 'example', 200),
        123,
    );

    $this->assertDatabaseHas('pdf_fetches', [
        'work_id' => $internalWorkId,
        'source_alias' => 'example',
        'source_url' => 'https://example.org/paper.pdf',
        'status' => 'success',
        'http_status' => 200,
        'file_path' => 'pdfs/paper.pdf',
        'duration_ms' => 123,
    ]);

    expect($this->repository->findSuccessfulPath($work->primaryId()))->toBe('pdfs/paper.pdf')
        ->and($this->repository->findSuccessfulPath(new WorkId(WorkIdNamespace::INTERNAL, $internalWorkId)))
        ->toBe('pdfs/paper.pdf');
});

it('returns null for successful path lookups when the work is not persisted', function (): void {
    $workId = new WorkId(WorkIdNamespace::DOI, '10.5555/missing');

    expect($this->repository->findSuccessfulPath($workId))->toBeNull();
});

it('detects recent failed fetches by work and source url', function (): void {
    $work = PersistenceFactory::makeWork(doi: '10.5555/recent-failure');
    $this->workRepository->save($work);

    $this->repository->save(
        $work->primaryId(),
        'https://example.org/failing.pdf',
        FullTextResult::failure('temporary failure', 'example', 503),
        456,
    );

    expect($this->repository->hasRecentFailure(
        $work->primaryId(),
        'https://example.org/failing.pdf',
        new DateTimeImmutable('-5 minutes'),
    ))->toBeTrue()
        ->and($this->repository->hasRecentFailure(
            $work->primaryId(),
            'https://example.org/other.pdf',
            new DateTimeImmutable('-5 minutes'),
        ))->toBeFalse()
        ->and($this->repository->hasRecentFailure(
            $work->primaryId(),
            'https://example.org/failing.pdf',
            new DateTimeImmutable('+1 minute'),
        ))->toBeFalse()
        ->and($this->repository->hasRecentFailure(
            new WorkId(WorkIdNamespace::DOI, '10.5555/missing'),
            'https://example.org/failing.pdf',
            new DateTimeImmutable('-5 minutes'),
        ))->toBeFalse();
});
