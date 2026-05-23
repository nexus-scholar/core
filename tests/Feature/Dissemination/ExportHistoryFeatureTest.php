<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Dissemination\Application\UseCase\ExportBibliography;
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Application\UseCase\ExportCitationGraph;
use Nexus\Dissemination\Application\UseCase\ExportCitationGraphHandler;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\ExportType;
use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Dissemination\Domain\Port\CitationGraphSerializerCollection;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use Nexus\Dissemination\Infrastructure\Serializer\CsvSerializer;
use Nexus\Dissemination\Infrastructure\Serializer\MbsoftCitationGraphSerializer;
use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Laravel\Model\WorkProviderModel;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Tests\Support\PersistenceFactory;

it('records bibliography export history after successful storage', function (): void {
    $work = PersistenceFactory::makeWork();
    $corpus = CorpusSlice::fromWorks($work);
    $storage = exportHistoryFeatureStorage();

    $handler = new ExportBibliographyHandler(
        new SerializerCollection(new CsvSerializer),
        $storage,
        app(ExportHistoryPort::class),
    );

    $path = $handler->handle(new ExportBibliography(
        corpus: $corpus,
        format: BibliographyFormat::CSV,
        filename: 'exports/works.csv',
        projectId: 'project-1',
        requestedBy: 'user-1',
        metadata: ['source' => 'feature'],
    ));

    expect($path)->toBe('exports/works.csv')
        ->and($storage->get('exports/works.csv'))->toContain('A Test Work');

    $this->assertDatabaseHas('export_histories', [
        'type' => ExportType::BIBLIOGRAPHY->value,
        'format' => BibliographyFormat::CSV->value,
        'filename' => 'exports/works.csv',
        'path' => 'exports/works.csv',
        'mime_type' => BibliographyFormat::CSV->mimeType(),
        'project_id' => 'project-1',
        'corpus_slice_id' => $corpus->id->value,
        'requested_by' => 'user-1',
    ]);

    $row = DB::table('export_histories')
        ->where('type', ExportType::BIBLIOGRAPHY->value)
        ->first();

    expect($row->size_bytes)->toBe(strlen($storage->get('exports/works.csv')))
        ->and(json_decode($row->metadata, true))->toBe(['source' => 'feature']);
});

it('marks draft project bibliography exports non-final and non-citable', function (): void {
    $project = PersistenceFactory::makeProject('Draft Export Project');
    $work = PersistenceFactory::makeWork(doi: '10.5555/draft-export', title: 'Draft Export Work');
    $storage = exportHistoryFeatureStorage();

    $handler = new ExportBibliographyHandler(
        new SerializerCollection(new CsvSerializer),
        $storage,
        app(ExportHistoryPort::class),
        app(CorpusLockPolicy::class),
    );

    $handler->handle(new ExportBibliography(
        corpus: CorpusSlice::fromWorks($work),
        format: BibliographyFormat::CSV,
        filename: 'exports/draft.csv',
        projectId: $project->id,
        requestedBy: 'user-1',
    ));

    $row = DB::table('export_histories')
        ->where('filename', 'exports/draft.csv')
        ->first();

    expect(json_decode($row->metadata, true))->toMatchArray([
        'project_locked' => false,
        'locked_at' => null,
        'corpus_snapshot_id' => null,
        'citable' => false,
        'final' => false,
    ]);
});

it('records snapshot-backed final metadata for locked project bibliography exports', function (): void {
    $project = PersistenceFactory::makeProject('Locked Export Project');
    $query = makeExportHistoryQuery($project->id, 'tomato segmentation');
    makeExportHistoryWork('export-history-work-1', 'Locked Export Work', 'openalex', [
        'doi' => '10.5555/locked-export',
    ]);
    linkExportHistoryWork($query->id, 'export-history-work-1', 'openalex', 1);

    app(ProjectLockLifecyclePort::class)->lock($project->id, actorId: 'reviewer-1', reason: 'final export');

    $work = PersistenceFactory::makeWork(doi: '10.5555/locked-export', title: 'Locked Export Work');
    $storage = exportHistoryFeatureStorage();
    $handler = new ExportBibliographyHandler(
        new SerializerCollection(new CsvSerializer),
        $storage,
        app(ExportHistoryPort::class),
        app(CorpusLockPolicy::class),
    );

    $handler->handle(new ExportBibliography(
        corpus: CorpusSlice::fromWorks($work),
        format: BibliographyFormat::CSV,
        filename: 'exports/locked.csv',
        projectId: $project->id,
        requestedBy: 'user-1',
    ));

    $row = DB::table('export_histories')
        ->where('filename', 'exports/locked.csv')
        ->first();
    $metadata = json_decode($row->metadata, true);

    expect($metadata)->toMatchArray([
        'project_locked' => true,
        'lock_status' => 'locked',
        'snapshot_work_count' => 1,
        'citable' => true,
        'final' => true,
    ])
        ->and($metadata['locked_at'])->not->toBeNull()
        ->and($metadata['corpus_snapshot_id'])->not->toBeNull();
});

it('records citation graph export history using graph-core exporters', function (): void {
    $source = PersistenceFactory::makeWork(doi: '10.5555/source', title: 'Source Work');
    $target = PersistenceFactory::makeWork(doi: '10.5555/target', title: 'Target Work');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-graph');
    $graph->addWork($source);
    $graph->addWork($target);
    $graph->recordCitation($source->primaryId(), $target->primaryId(), 2.5);
    $storage = exportHistoryFeatureStorage();

    $handler = new ExportCitationGraphHandler(
        new CitationGraphSerializerCollection(new MbsoftCitationGraphSerializer),
        $storage,
        app(ExportHistoryPort::class),
    );

    $path = $handler->handle(new ExportCitationGraph(
        graph: $graph,
        format: NetworkExportFormat::GRAPHML,
        filename: 'exports/network.graphml',
        requestedBy: 'user-2',
        metadata: ['layout' => 'none'],
    ));

    expect($path)->toBe('exports/network.graphml')
        ->and($storage->get('exports/network.graphml'))->toContain('source="doi:10.5555/source"')
        ->and($storage->get('exports/network.graphml'))->toContain('target="doi:10.5555/target"');

    $this->assertDatabaseHas('export_histories', [
        'type' => ExportType::NETWORK->value,
        'format' => NetworkExportFormat::GRAPHML->value,
        'filename' => 'exports/network.graphml',
        'path' => 'exports/network.graphml',
        'mime_type' => NetworkExportFormat::GRAPHML->mimeType(),
        'project_id' => 'project-graph',
        'citation_graph_id' => $graph->id->value,
        'requested_by' => 'user-2',
    ]);

    $row = DB::table('export_histories')
        ->where('type', ExportType::NETWORK->value)
        ->first();

    expect($row->size_bytes)->toBe(strlen($storage->get('exports/network.graphml')))
        ->and(json_decode($row->metadata, true))->toBe(['layout' => 'none']);
});

function exportHistoryFeatureStorage(): FileStoragePort
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

function makeExportHistoryQuery(string $projectId, string $queryText): SearchQueryModel
{
    return SearchQueryModel::create([
        'id' => (string) Str::uuid(),
        'project_id' => $projectId,
        'query_text' => $queryText,
        'max_results' => 50,
        'cache_key' => hash('sha256', $projectId.$queryText),
        'status' => 'completed',
    ]);
}

/**
 * @param  array<string, string>  $identifiers
 */
function makeExportHistoryWork(
    string $id,
    string $title,
    string $provider,
    array $identifiers,
): ScholarlyWorkModel {
    $work = ScholarlyWorkModel::create([
        'id' => $id,
        'title' => $title,
        'abstract' => 'Abstract for '.$title,
        'year' => 2025,
        'venue_name' => 'Plant Methods',
        'retrieved_at' => now(),
    ]);

    foreach ($identifiers as $namespace => $value) {
        WorkExternalIdModel::create([
            'id' => (string) Str::uuid(),
            'work_id' => $id,
            'namespace' => $namespace,
            'value' => strtolower($value),
            'is_primary' => $namespace === 'doi',
        ]);
    }

    WorkProviderModel::create([
        'id' => (string) Str::uuid(),
        'work_id' => $id,
        'provider_alias' => $provider,
        'provider_work_id' => $identifiers[$provider] ?? null,
        'metadata' => ['source' => 'test'],
        'last_seen_at' => now(),
    ]);

    return $work;
}

function linkExportHistoryWork(string $searchQueryId, string $workId, string $provider, int $rank): void
{
    QueryWorkModel::create([
        'id' => (string) Str::uuid(),
        'search_query_id' => $searchQueryId,
        'work_id' => $workId,
        'provider_alias' => $provider,
        'provider_work_id' => $provider.'-'.$workId,
        'rank' => $rank,
    ]);
}
