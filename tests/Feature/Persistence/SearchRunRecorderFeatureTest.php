<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\ProviderStat;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Tests\Support\PersistenceFactory;

it('persists a complete search trace through the Laravel recorder', function (): void {
    $project = PersistenceFactory::makeProject();

    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::OPENALEX, 'W123'),
            new WorkId(WorkIdNamespace::DOI, '10.1234/search-trace'),
        ]),
        title: 'A Search Trace Work',
        sourceProvider: 'openalex',
        year: 2024,
        abstract: 'Traceable abstract.',
    );

    app()->instance(SearchAggregatorPort::class, new class($work) implements SearchAggregatorPort
    {
        public function __construct(private readonly ScholarlyWork $work) {}

        public function aggregate(SearchQuery $query): AggregatedResult
        {
            return new AggregatedResult(
                corpus: CorpusSlice::fromWorks($this->work),
                providerStats: [
                    new ProviderStat('openalex', 1, 18),
                    new ProviderStat('arxiv', 0, 7, 'rate limited'),
                ],
                totalRaw: 1,
                durationMs: 25,
            );
        }
    });

    $result = app(SearchExecutorPort::class)->handle(new SearchAcrossProviders(
        query: 'texture analysis',
        projectId: $project->id,
        maxResults: 10,
        providerAliases: ['openalex', 'arxiv'],
    ));

    expect($result->corpus->count())->toBe(1);

    $query = DB::table('search_queries')->where('project_id', $project->id)->first();
    expect($query)->not->toBeNull()
        ->and($query->query_text)->toBe('texture analysis')
        ->and($query->status)->toBe('completed')
        ->and($query->total_raw)->toBe(1)
        ->and($query->total_unique)->toBe(1)
        ->and(json_decode($query->provider_aliases, true))->toBe(['openalex', 'arxiv']);

    expect(DB::table('search_query_providers')->where('search_query_id', $query->id)->count())->toBe(2);
    $this->assertDatabaseHas('search_query_providers', [
        'search_query_id' => $query->id,
        'provider_alias' => 'openalex',
        'total_raw' => 1,
        'error_message' => null,
    ]);
    $this->assertDatabaseHas('search_query_providers', [
        'search_query_id' => $query->id,
        'provider_alias' => 'arxiv',
        'total_raw' => 0,
        'error_message' => 'rate limited',
    ]);

    $internalWorkId = DB::table('work_external_ids')
        ->where('namespace', 'openalex')
        ->where('value', 'w123')
        ->value('work_id');

    expect($internalWorkId)->not->toBeNull();

    $this->assertDatabaseHas('work_providers', [
        'work_id' => $internalWorkId,
        'provider_alias' => 'openalex',
        'provider_work_id' => 'w123',
    ]);
    $this->assertDatabaseHas('query_works', [
        'search_query_id' => $query->id,
        'work_id' => $internalWorkId,
        'provider_alias' => 'openalex',
        'provider_work_id' => 'w123',
        'rank' => 1,
    ]);
});
