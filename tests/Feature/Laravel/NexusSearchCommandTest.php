<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Aggregator\ProviderStat;
use Nexus\Search\Application\Aggregator\SearchAggregatorPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Tests\Support\PersistenceFactory;

it('passes selected providers from the CLI into the reusable search flow and persists the trace', function (): void {
    $project = PersistenceFactory::makeProject();
    $received = (object) ['query' => null];

    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::OPENALEX, 'W456')]),
        title: 'CLI Provider Selection Work',
        sourceProvider: 'openalex',
    );

    app()->instance(SearchAggregatorPort::class, new class($received, $work) implements SearchAggregatorPort
    {
        public function __construct(private readonly object $received, private readonly ScholarlyWork $work) {}

        public function aggregate(SearchQuery $query): AggregatedResult
        {
            $this->received->query = $query;

            return new AggregatedResult(
                corpus: CorpusSlice::fromWorks($this->work),
                providerStats: [new ProviderStat('openalex', 1, 5)],
                totalRaw: 1,
                durationMs: 6,
            );
        }
    });

    $this->artisan('nexus:search', [
        'query' => 'texture analysis',
        '--project' => $project->id,
        '--providers' => 'OpenAlex, openalex',
        '--max' => 5,
    ])->assertExitCode(0);

    expect($received->query)->toBeInstanceOf(SearchQuery::class)
        ->and($received->query->providerAliases)->toBe(['openalex'])
        ->and($received->query->maxResults)->toBe(5);

    $row = DB::table('search_queries')->where('project_id', $project->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('completed')
        ->and(json_decode($row->provider_aliases, true))->toBe(['openalex']);
});

it('runs query files through the reusable YAML parser and honors filters', function (): void {
    $project = PersistenceFactory::makeProject();
    $receivedTerms = [];

    app()->instance(SearchAggregatorPort::class, new class($receivedTerms) implements SearchAggregatorPort
    {
        public function __construct(private array &$receivedTerms) {}

        public function aggregate(SearchQuery $query): AggregatedResult
        {
            $this->receivedTerms[] = $query->term->value;

            return new AggregatedResult(
                corpus: CorpusSlice::empty(),
                providerStats: [new ProviderStat('openalex', 0, 1)],
                totalRaw: 0,
                durationMs: 1,
            );
        }
    });

    $fixture = realpath(__DIR__.'/../../Fixture/search_plans/nexus_cli_v4_searches.yml');

    $this->artisan('nexus:search', [
        '--file' => $fixture,
        '--project' => $project->id,
        '--only' => 'TX_CORE01',
        '--providers' => 'openalex',
    ])->assertExitCode(0);

    expect($receivedTerms)->toHaveCount(1)
        ->and($receivedTerms[0])->toContain('texture analysis');

    expect(DB::table('search_queries')->where('project_id', $project->id)->count())->toBe(1);
});
