<?php

declare(strict_types=1);

use Nexus\Dissemination\Application\UseCase\ExportBibliography;
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use Nexus\Dissemination\Infrastructure\Serializer\CsvSerializer;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\ValueObject\ProjectLockState;
use Tests\Support\PersistenceFactory;

it('records project lock state in bibliography export history metadata', function (): void {
    $history = new ExportLockMetadataHistory;

    $handler = new ExportBibliographyHandler(
        new SerializerCollection(new CsvSerializer),
        new ExportLockMetadataStorage,
        $history,
        new CorpusLockPolicy(
            new ExportLockMetadataLocks(['project-1' => true]),
            new ExportLockMetadataMembership,
            new ExportLockMetadataLifecycle(new ProjectLockState(
                projectId: 'project-1',
                isLocked: true,
                status: 'locked',
                lockedAt: new DateTimeImmutable('2026-05-22T12:00:00+00:00'),
            )),
        ),
    );

    $handler->handle(new ExportBibliography(
        corpus: CorpusSlice::fromWorks(PersistenceFactory::makeWork()),
        format: BibliographyFormat::CSV,
        filename: 'exports/locked.csv',
        projectId: 'project-1',
        metadata: ['source' => 'test'],
    ));

    expect($history->records[0]->metadata)->toMatchArray([
        'source' => 'test',
        'project_locked' => true,
        'locked_at' => '2026-05-22T12:00:00+00:00',
        'lock_status' => 'locked',
        'citable' => true,
    ]);
});

final class ExportLockMetadataHistory implements ExportHistoryPort
{
    /** @var list<ExportHistoryRecord> */
    public array $records = [];

    public function record(ExportHistoryRecord $record): void
    {
        $this->records[] = $record;
    }
}

final class ExportLockMetadataStorage implements FileStoragePort
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

final class ExportLockMetadataLocks implements ProjectLockPort
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

final class ExportLockMetadataMembership implements ProjectWorkMembershipPort
{
    public function missingWorkIds(string $projectId, array $workIds): array
    {
        return [];
    }
}

final class ExportLockMetadataLifecycle implements ProjectLockLifecyclePort
{
    public function __construct(private readonly ProjectLockState $state) {}

    public function lock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState
    {
        return $this->state;
    }

    public function unlock(string $projectId, ?string $actorId = null, ?string $reason = null, array $metadata = []): ProjectLockState
    {
        return $this->state;
    }

    public function status(string $projectId): ProjectLockState
    {
        return $this->state;
    }
}
