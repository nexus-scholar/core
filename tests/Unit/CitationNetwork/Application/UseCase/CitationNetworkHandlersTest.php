<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder;
use Nexus\CitationNetwork\Application\Exception\CitationGraphNotFound;
use Nexus\CitationNetwork\Application\UseCase\AnalyzeNetwork;
use Nexus\CitationNetwork\Application\UseCase\AnalyzeNetworkHandler;
use Nexus\CitationNetwork\Application\UseCase\BuildCitationGraph;
use Nexus\CitationNetwork\Application\UseCase\BuildCitationGraphHandler;
use Nexus\CitationNetwork\Application\UseCase\FindShortestCitationPath;
use Nexus\CitationNetwork\Application\UseCase\FindShortestCitationPathHandler;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphId;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Domain\CitationPath;
use Nexus\CitationNetwork\Domain\NetworkMetrics;
use Nexus\CitationNetwork\Domain\Port\CitationGraphRepositoryPort;
use Nexus\CitationNetwork\Domain\Port\GraphAlgorithmPort;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

function citationUseCaseTestWork(string $doi, string $title): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title: $title,
        sourceProvider: 'test',
    );
}

it('builds and persists direct citation graphs through the application handler', function (): void {
    $workA = citationUseCaseTestWork('10.2000/a', 'A');
    $workB = citationUseCaseTestWork('10.2000/b', 'B');
    $repository = new CitationHandlerTestRepository;
    $handler = new BuildCitationGraphHandler(new CitationGraphBuilder, $repository);

    $graph = $handler->handle(BuildCitationGraph::directCitation(
        'project-1',
        [$workA, $workB],
        [$workA->primaryId()->toString() => [$workB->primaryId()->toString()]],
    ));

    expect($graph->type)->toBe(CitationGraphType::CITATION)
        ->and($graph->edgeCount())->toBe(1)
        ->and($repository->saved[$graph->id->toString()] ?? null)->toBe($graph);
});

it('can build co-citation graphs without persisting them', function (): void {
    $workA = citationUseCaseTestWork('10.2000/a', 'A');
    $workB = citationUseCaseTestWork('10.2000/b', 'B');
    $repository = new CitationHandlerTestRepository;
    $handler = new BuildCitationGraphHandler(new CitationGraphBuilder, $repository);

    $graph = $handler->handle(BuildCitationGraph::coCitation(
        'project-1',
        [$workA, $workB],
        ['s2:citing-one' => [$workA->primaryId()->toString(), $workB->primaryId()->toString()]],
        persist: false,
    ));

    expect($graph->type)->toBe(CitationGraphType::CO_CITATION)
        ->and($graph->edgeCount())->toBe(1)
        ->and($repository->saved)->toBe([]);
});

it('checks project membership before persisting graphs for a locked corpus', function (): void {
    $workA = citationUseCaseTestWork('10.2000/a', 'A');
    $repository = new CitationHandlerTestRepository;
    $handler = new BuildCitationGraphHandler(
        new CitationGraphBuilder,
        $repository,
        new CorpusLockPolicy(
            new CitationGraphTestLocks(['project-1' => true]),
            new CitationGraphTestMembership(['doi:10.2000/a']),
        ),
    );

    expect(fn () => $handler->handle(BuildCitationGraph::directCitation(
        'project-1',
        [$workA],
        [],
    )))->toThrow(InvalidArgumentException::class, 'doi:10.2000/a');

    expect($repository->saved)->toBe([]);
});

it('analyzes a persisted graph and stores the metrics snapshot by default', function (): void {
    $workA = citationUseCaseTestWork('10.2000/a', 'A');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');
    $graph->addWork($workA);
    $repository = new CitationHandlerTestRepository;
    $repository->save($graph);
    $metrics = new NetworkMetrics(pageRank: [$workA->primaryId()->toString() => 1.0], nodeCount: 1);
    $algorithms = new CitationHandlerTestAlgorithms($metrics);

    $result = (new AnalyzeNetworkHandler($repository, $algorithms))
        ->handle(new AnalyzeNetwork($graph->id));

    expect($result)->toBe($metrics)
        ->and($algorithms->computedGraph)->toBe($graph)
        ->and($repository->metrics[$graph->id->toString()] ?? null)->toBe($metrics);
});

it('can analyze without persisting the metrics snapshot', function (): void {
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');
    $repository = new CitationHandlerTestRepository;
    $repository->save($graph);
    $metrics = new NetworkMetrics(nodeCount: 0);
    $algorithms = new CitationHandlerTestAlgorithms($metrics);

    (new AnalyzeNetworkHandler($repository, $algorithms))
        ->handle(new AnalyzeNetwork($graph->id, persistMetrics: false));

    expect($repository->metrics)->toBe([]);
});

it('throws a clear exception when analyzing a missing graph', function (): void {
    $repository = new CitationHandlerTestRepository;
    $algorithms = new CitationHandlerTestAlgorithms(new NetworkMetrics);
    $missingId = CitationGraphId::generate();

    expect(fn () => (new AnalyzeNetworkHandler($repository, $algorithms))
        ->handle(new AnalyzeNetwork($missingId)))
        ->toThrow(CitationGraphNotFound::class);
});

it('finds shortest citation paths through the application handler', function (): void {
    $workA = citationUseCaseTestWork('10.2000/a', 'A');
    $workB = citationUseCaseTestWork('10.2000/b', 'B');
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');
    $graph->addWork($workA);
    $graph->addWork($workB);
    $repository = new CitationHandlerTestRepository;
    $repository->save($graph);
    $path = new CitationPath([
        $workA->primaryId()->toString(),
        $workB->primaryId()->toString(),
    ], 1.0);
    $algorithms = new CitationHandlerTestAlgorithms(new NetworkMetrics, $path);

    $result = (new FindShortestCitationPathHandler($repository, $algorithms))
        ->handle(new FindShortestCitationPath($graph->id, $workA->primaryId(), $workB->primaryId()));

    expect($result)->toBe($path)
        ->and($algorithms->shortestPathGraph)->toBe($graph)
        ->and($algorithms->shortestPathSource?->equals($workA->primaryId()))->toBeTrue()
        ->and($algorithms->shortestPathTarget?->equals($workB->primaryId()))->toBeTrue();
});

final class CitationHandlerTestRepository implements CitationGraphRepositoryPort
{
    /** @var array<string, CitationGraph> */
    public array $saved = [];

    /** @var array<string, NetworkMetrics> */
    public array $metrics = [];

    public function save(CitationGraph $graph): void
    {
        $this->saved[$graph->id->toString()] = $graph;
    }

    public function findById(CitationGraphId $id): ?CitationGraph
    {
        return $this->saved[$id->toString()] ?? null;
    }

    public function findByProjectId(string $projectId): array
    {
        return array_values(array_filter(
            $this->saved,
            fn (CitationGraph $graph): bool => $graph->projectId === $projectId,
        ));
    }

    public function saveMetrics(CitationGraphId $id, NetworkMetrics $metrics): void
    {
        $this->metrics[$id->toString()] = $metrics;
    }

    public function delete(CitationGraphId $id): void
    {
        unset($this->saved[$id->toString()], $this->metrics[$id->toString()]);
    }
}

final class CitationHandlerTestAlgorithms implements GraphAlgorithmPort
{
    public ?CitationGraph $computedGraph = null;

    public ?CitationGraph $shortestPathGraph = null;

    public ?WorkId $shortestPathSource = null;

    public ?WorkId $shortestPathTarget = null;

    public function __construct(
        private readonly NetworkMetrics $metrics,
        private readonly ?CitationPath $path = null,
    ) {}

    public function compute(CitationGraph $graph): NetworkMetrics
    {
        $this->computedGraph = $graph;

        return $this->metrics;
    }

    public function shortestPath(CitationGraph $graph, WorkId $source, WorkId $target): ?CitationPath
    {
        $this->shortestPathGraph = $graph;
        $this->shortestPathSource = $source;
        $this->shortestPathTarget = $target;

        return $this->path;
    }
}

final class CitationGraphTestLocks implements ProjectLockPort
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

final class CitationGraphTestMembership implements ProjectWorkMembershipPort
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
