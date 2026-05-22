<?php

declare(strict_types=1);

use Nexus\Screening\Application\Llm\LlmRequest;
use Nexus\Screening\Application\Llm\LlmResponse;
use Nexus\Screening\Application\Port\LlmClientPort;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningPromptRendererPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningVoteRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningWorkSourcePort;
use Nexus\Screening\Application\Prompt\ScreeningPrompt;
use Nexus\Screening\Application\UseCase\ScreenCorpusCommand;
use Nexus\Screening\Application\UseCase\ScreenCorpusHandler;
use Nexus\Screening\Application\UseCase\ScreenWorkHandler;
use Nexus\Screening\Domain\CouncilDecisionAggregator;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;
use Nexus\Screening\Domain\ScreeningVote;
use Nexus\Screening\Domain\ScreeningWork;

it('screens a project corpus and records run lifecycle counts', function (): void {
    $source = new InMemoryScreeningWorkSource([
        corpusWork('work-1', 'Tomato instance segmentation with limited labels'),
        corpusWork('work-2', 'Retinal vessel segmentation in medical imaging'),
    ]);
    $runs = new InMemoryScreeningRunRepository;
    $decisions = new InMemoryScreeningDecisionRepositoryForCorpus;
    $votes = new InMemoryScreeningVoteRepositoryForCorpus;
    $llm = new QueueScreeningLlmClient([
        [
            'decision' => 'include',
            'confidence' => 0.93,
            'reason' => 'Direct tomato instance segmentation match.',
            'evidence' => ['Tomato instance segmentation'],
            'uncertainty' => [],
            'exclusion_basis' => [],
        ],
        [
            'decision' => 'exclude',
            'confidence' => 0.97,
            'reason' => 'Medical imaging is outside the review scope.',
            'evidence' => ['medical imaging'],
            'uncertainty' => [],
            'exclusion_basis' => ['medical imaging'],
        ],
    ]);
    $screenWork = new ScreenWorkHandler(
        $llm,
        new FixedScreeningPromptRendererForCorpus,
        new CouncilDecisionAggregator,
        $decisions,
        $votes,
    );

    $handler = new ScreenCorpusHandler($source, $runs, $screenWork);

    $result = $handler->handle(new ScreenCorpusCommand(
        projectId: 'project-1',
        criteria: corpusCriteria(),
        screeningRunId: 'run-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        mode: ScreeningRunMode::LLM_SINGLE,
        model: 'openai/gpt-4.1-mini',
        limit: 10,
        workIds: ['work-1', 'work-2'],
        queryIds: ['query-1'],
    ));

    expect($runs->started[0]->id)->toBe('run-1')
        ->and($runs->started[0]->criteria->hash())->toBe(corpusCriteria()->hash())
        ->and($runs->completed['run-1']['total'])->toBe(2)
        ->and($runs->completed['run-1']['included'])->toBe(1)
        ->and($runs->completed['run-1']['excluded'])->toBe(1)
        ->and($source->calls[0])->toMatchArray([
            'projectId' => 'project-1',
            'limit' => 10,
            'workIds' => ['work-1', 'work-2'],
            'queryIds' => ['query-1'],
        ])
        ->and($decisions->recorded)->toHaveCount(2)
        ->and($votes->recorded)->toHaveCount(2)
        ->and($result->runId)->toBe('run-1')
        ->and($result->total)->toBe(2)
        ->and($result->included)->toBe(1)
        ->and($result->excluded)->toBe(1)
        ->and($result->hasFailures())->toBeFalse();
});

it('marks a corpus run as failed when work loading fails', function (): void {
    $runs = new InMemoryScreeningRunRepository;
    $handler = new ScreenCorpusHandler(
        new FailingScreeningWorkSource,
        $runs,
        new ScreenWorkHandler(
            new QueueScreeningLlmClient([]),
            new FixedScreeningPromptRendererForCorpus,
            new CouncilDecisionAggregator,
            new InMemoryScreeningDecisionRepositoryForCorpus,
            new InMemoryScreeningVoteRepositoryForCorpus,
        ),
    );

    expect(fn () => $handler->handle(new ScreenCorpusCommand(
        projectId: 'project-1',
        criteria: corpusCriteria(),
        screeningRunId: 'run-failed',
        model: 'openai/gpt-4.1-mini',
    )))->toThrow(RuntimeException::class, 'source unavailable');

    expect($runs->failed['run-failed'])->toBe('source unavailable');
});

function corpusCriteria(): ScreeningCriteria
{
    return ScreeningCriteria::fromArray([
        'include' => ['tomato instance segmentation', 'label-efficient crop vision'],
        'exclude' => ['medical imaging', 'classification only'],
    ]);
}

function corpusWork(string $id, string $title): ScreeningWork
{
    return new ScreeningWork(
        id: $id,
        title: $title,
        abstract: 'Short abstract for '.$title,
        year: 2025,
        venue: 'Test Venue',
        sourceProvider: 'openalex',
    );
}

final class InMemoryScreeningWorkSource implements ScreeningWorkSourcePort
{
    /** @var list<array{projectId: string, limit: ?int, workIds: list<string>, queryIds: list<string>}> */
    public array $calls = [];

    /**
     * @param  list<ScreeningWork>  $works
     */
    public function __construct(private readonly array $works) {}

    public function forProject(string $projectId, ?int $limit = null, array $workIds = [], array $queryIds = []): array
    {
        $this->calls[] = compact('projectId', 'limit', 'workIds', 'queryIds');

        return $limit === null ? $this->works : array_slice($this->works, 0, $limit);
    }
}

final class FailingScreeningWorkSource implements ScreeningWorkSourcePort
{
    public function forProject(string $projectId, ?int $limit = null, array $workIds = [], array $queryIds = []): array
    {
        throw new RuntimeException('source unavailable');
    }
}

final class FixedScreeningPromptRendererForCorpus implements ScreeningPromptRendererPort
{
    public function render(
        ScreeningWork $work,
        ScreeningCriteria $criteria,
        ScreeningStage $stage,
        array $context = [],
    ): ScreeningPrompt {
        return new ScreeningPrompt(
            messages: [
                ['role' => 'system', 'content' => 'Screen title and abstract.'],
                ['role' => 'user', 'content' => $work->title],
            ],
            responseSchema: [
                'type' => 'object',
                'properties' => ['decision' => ['type' => 'string']],
                'required' => ['decision'],
            ],
            hash: 'prompt-hash',
        );
    }
}

final class InMemoryScreeningRunRepository implements ScreeningRunRepositoryPort
{
    /** @var list<ScreeningRun> */
    public array $started = [];

    /** @var array<string, array<string, int>> */
    public array $completed = [];

    /** @var array<string, string> */
    public array $failed = [];

    public function start(ScreeningRun $run): void
    {
        $this->started[] = $run;
    }

    public function complete(string $screeningRunId, array $counts): void
    {
        $this->completed[$screeningRunId] = $counts;
    }

    public function fail(string $screeningRunId, string $message): void
    {
        $this->failed[$screeningRunId] = $message;
    }
}

final class QueueScreeningLlmClient implements LlmClientPort
{
    /**
     * @param  list<array<string, mixed>>  $responses
     */
    public function __construct(private array $responses) {}

    public function completeJson(LlmRequest $request): LlmResponse
    {
        $content = array_shift($this->responses)
            ?? throw new RuntimeException('No queued screening response.');

        return new LlmResponse(
            provider: 'openrouter',
            model: $request->model,
            content: $content,
            rawResponse: json_encode($content, JSON_THROW_ON_ERROR),
        );
    }
}

final class InMemoryScreeningDecisionRepositoryForCorpus implements ScreeningDecisionRepositoryPort
{
    /** @var list<ScreeningVerdict> */
    public array $recorded = [];

    public function record(ScreeningVerdict $verdict): void
    {
        $this->recorded[] = $verdict;
    }

    public function latestForWork(string $projectId, string $workId, ScreeningStage $stage): ?ScreeningVerdict
    {
        return null;
    }
}

final class InMemoryScreeningVoteRepositoryForCorpus implements ScreeningVoteRepositoryPort
{
    /** @var list<ScreeningVote> */
    public array $recorded = [];

    public function record(ScreeningVote $vote): void
    {
        $this->recorded[] = $vote;
    }

    public function forDecision(string $screeningDecisionId): array
    {
        return [];
    }
}
