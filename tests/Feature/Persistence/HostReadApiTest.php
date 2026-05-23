<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\ExportType;
use Nexus\Dissemination\Domain\FullTextStatus;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort;
use Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Laravel\Job\SearchJob;
use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Port\JobLifecycleReaderPort;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;
use Nexus\Shared\ValueObject\JobLifecycleStatus;
use Tests\Support\PersistenceFactory;

it('reads export history by id and latest filters', function (): void {
    $writer = app(ExportHistoryPort::class);
    $reader = app(ExportHistoryReaderPort::class);

    $old = ExportHistoryRecord::create(
        type: ExportType::BIBLIOGRAPHY,
        format: BibliographyFormat::CSV->value,
        filename: 'exports/old.csv',
        path: 'exports/old.csv',
        mimeType: BibliographyFormat::CSV->mimeType(),
        sizeBytes: 12,
        projectId: 'project-read-a',
        metadata: ['citable' => false],
        createdAt: new DateTimeImmutable('2026-05-23T10:00:00+00:00'),
    );
    $new = ExportHistoryRecord::create(
        type: ExportType::BIBLIOGRAPHY,
        format: BibliographyFormat::CSV->value,
        filename: 'exports/new.csv',
        path: 'exports/new.csv',
        mimeType: BibliographyFormat::CSV->mimeType(),
        sizeBytes: 15,
        projectId: 'project-read-a',
        requestedBy: 'reviewer-1',
        metadata: ['citable' => true],
        createdAt: new DateTimeImmutable('2026-05-23T11:00:00+00:00'),
    );
    $otherProject = ExportHistoryRecord::create(
        type: ExportType::NETWORK,
        format: 'graphml',
        filename: 'exports/network.graphml',
        path: 'exports/network.graphml',
        mimeType: 'application/xml',
        sizeBytes: 20,
        projectId: 'project-read-b',
        createdAt: new DateTimeImmutable('2026-05-23T12:00:00+00:00'),
    );

    $writer->record($old);
    $writer->record($new);
    $writer->record($otherProject);

    expect($reader->find($new->id))
        ->not->toBeNull()
        ->and($reader->find($new->id)?->filename)->toBe('exports/new.csv')
        ->and($reader->find($new->id)?->metadata)->toBe(['citable' => true])
        ->and($reader->find('missing-export'))->toBeNull();

    $latest = $reader->latest('project-read-a', ExportType::BIBLIOGRAPHY->value, 1);

    expect($latest)->toHaveCount(1)
        ->and($latest[0]->id)->toBe($new->id)
        ->and($latest[0]->requestedBy)->toBe('reviewer-1');
});

it('reads job lifecycle records by run and project status', function (): void {
    $writer = app(JobLifecycleRecorderPort::class);
    $reader = app(JobLifecycleReaderPort::class);

    $writer->record(JobLifecycleRecord::started(
        runId: 'run-read-1',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-jobs'],
        occurredAt: new DateTimeImmutable('2026-05-23T10:00:00+00:00'),
    ));
    $writer->record(JobLifecycleRecord::progressed(
        runId: 'run-read-1',
        jobName: 'search',
        jobClass: SearchJob::class,
        progressKey: 'provider:openalex',
        context: ['project_id' => 'project-jobs'],
        summary: ['count' => 10],
        durationMs: 50,
        occurredAt: new DateTimeImmutable('2026-05-23T10:01:00+00:00'),
    ));
    $writer->record(JobLifecycleRecord::completed(
        runId: 'run-read-1',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => 'project-jobs'],
        summary: ['success_count' => 1],
        durationMs: 75,
        occurredAt: new DateTimeImmutable('2026-05-23T10:02:00+00:00'),
    ));

    $forRun = $reader->forRun('run-read-1');
    $latestForProject = $reader->latestForProject('project-jobs', 2);

    expect($forRun)->toHaveCount(3)
        ->and($forRun[0]->status)->toBe(JobLifecycleStatus::STARTED)
        ->and($forRun[2]->status)->toBe(JobLifecycleStatus::COMPLETED)
        ->and($latestForProject)->toHaveCount(2)
        ->and($latestForProject[0]->status)->toBe(JobLifecycleStatus::COMPLETED)
        ->and($reader->latestStatusForRun('run-read-1'))->toBe(JobLifecycleStatus::COMPLETED)
        ->and($reader->latestStatusForRun('missing-run'))->toBeNull();
});

it('reads full-text fetch records by work and locked project corpus authority', function (): void {
    $project = PersistenceFactory::makeProject('Host Reader Project');
    $query = hostReadCreateQuery($project->id);
    $workRepository = app(WorkRepositoryPort::class);
    $fetchWriter = app(PdfFetchRepositoryPort::class);
    $fetchReader = app(FullTextFetchReaderPort::class);

    $included = PersistenceFactory::makeWork(doi: '10.5555/host-read-included', title: 'Included Work');
    $excluded = PersistenceFactory::makeWork(doi: '10.5555/host-read-excluded', title: 'Excluded Work');

    $workRepository->save($included);
    $workRepository->save($excluded);

    $includedInternalId = hostReadInternalIdForDoi('10.5555/host-read-included');
    $excludedInternalId = hostReadInternalIdForDoi('10.5555/host-read-excluded');

    hostReadLinkWork($query->id, $includedInternalId, 'openalex', 1);
    app(ProjectLockLifecyclePort::class)->lock($project->id, 'reviewer-1', 'freeze host read API test');
    hostReadLinkWork($query->id, $excludedInternalId, 'openalex', 2);

    $fetchWriter->save(
        $included->primaryId(),
        'https://example.org/included.pdf',
        FullTextResult::success('pdfs/included.pdf', 'unpaywall', 200, ['license' => 'cc-by']),
        30,
    );
    $fetchWriter->save(
        $excluded->primaryId(),
        'https://example.org/excluded.pdf',
        FullTextResult::failure('not found', 'unpaywall', 404),
        40,
    );

    $byWork = $fetchReader->forWork($included->primaryId()->toString());
    $byProject = $fetchReader->forProject($project->id);

    expect($byWork)->toHaveCount(1)
        ->and($byWork[0]->workId)->toBe($includedInternalId)
        ->and($byWork[0]->status)->toBe(FullTextStatus::SUCCESS)
        ->and($byWork[0]->metadata)->toBe(['license' => 'cc-by'])
        ->and($byProject)->toHaveCount(1)
        ->and($byProject[0]->workId)->toBe($includedInternalId)
        ->and($fetchReader->forWork('doi:10.5555/missing'))->toBe([]);
});

function hostReadCreateQuery(string $projectId): SearchQueryModel
{
    return SearchQueryModel::create([
        'id' => (string) Str::uuid(),
        'project_id' => $projectId,
        'query_text' => 'host read api',
        'max_results' => 50,
        'cache_key' => hash('sha256', $projectId.'host read api'),
        'status' => 'completed',
    ]);
}

function hostReadInternalIdForDoi(string $doi): string
{
    return (string) WorkExternalIdModel::query()
        ->where('namespace', 'doi')
        ->where('value', strtolower($doi))
        ->value('work_id');
}

function hostReadLinkWork(string $searchQueryId, string $workId, string $provider, int $rank): void
{
    QueryWorkModel::create([
        'id' => (string) Str::uuid(),
        'search_query_id' => $searchQueryId,
        'work_id' => $workId,
        'provider_alias' => $provider,
        'provider_work_id' => $provider.'-'.$rank,
        'rank' => $rank,
    ]);
}
