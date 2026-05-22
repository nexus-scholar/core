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
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\ValueObject\WorkId;
use Tests\Support\PersistenceFactory;

it('checks locked project membership before storing full-text artifacts', function (): void {
    $downloader = new RetrieveLockTestDownloader;

    $handler = new RetrieveFullTextHandler(
        new FullTextSourceCollection(new RetrieveLockTestSource),
        new RetrieveLockTestStorage,
        $downloader,
        new RetrieveLockTestRepository,
        new CorpusLockPolicy(
            new RetrieveLockTestLocks(['project-1' => true]),
            new RetrieveLockTestMembership(['doi:10.5555/locked']),
        ),
    );

    expect(fn () => $handler->handle(new RetrieveFullText(
        PersistenceFactory::makeWork(doi: '10.5555/locked'),
        destinationFolder: 'pdfs',
        projectId: 'project-1',
    )))->toThrow(InvalidArgumentException::class, 'doi:10.5555/locked');

    expect($downloader->downloads)->toBe(0);
});

final class RetrieveLockTestSource implements FullTextSourcePort
{
    public function resolve(ScholarlyWork $work): ?string
    {
        return 'https://example.org/full-text.pdf';
    }

    public function alias(): string
    {
        return 'test';
    }

    public function supports(ScholarlyWork $work): bool
    {
        return true;
    }
}

final class RetrieveLockTestStorage implements FileStoragePort
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
}

final class RetrieveLockTestDownloader implements PdfDownloaderPort
{
    public int $downloads = 0;

    public function download(string $url): DownloadResult
    {
        $this->downloads++;

        return new DownloadResult('%PDF-1.4', 200, 'application/pdf');
    }
}

final class RetrieveLockTestRepository implements PdfFetchRepositoryPort
{
    public function save(WorkId $workId, string $sourceUrl, FullTextResult $result, int $durationMs): void {}

    public function findSuccessfulPath(WorkId $workId): ?string
    {
        return null;
    }

    public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
    {
        return false;
    }
}

final class RetrieveLockTestLocks implements ProjectLockPort
{
    /**
     * @param  array<string, bool>  $locks
     */
    public function __construct(private readonly array $locks) {}

    public function isLocked(string $projectId): bool
    {
        return $this->locks[$projectId] ?? false;
    }
}

final class RetrieveLockTestMembership implements ProjectWorkMembershipPort
{
    /**
     * @param  list<string>  $missing
     */
    public function __construct(private readonly array $missing) {}

    public function missingWorkIds(string $projectId, array $workIds): array
    {
        return array_values(array_intersect($workIds, $this->missing));
    }
}
