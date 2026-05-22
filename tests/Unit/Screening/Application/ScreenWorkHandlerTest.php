<?php

declare(strict_types=1);

use Nexus\Screening\Application\Llm\LlmRequest;
use Nexus\Screening\Application\Llm\LlmResponse;
use Nexus\Screening\Application\Port\LlmClientPort;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningPromptRendererPort;
use Nexus\Screening\Application\Port\ScreeningVoteRepositoryPort;
use Nexus\Screening\Application\Prompt\ScreeningPrompt;
use Nexus\Screening\Application\UseCase\ScreenWorkCommand;
use Nexus\Screening\Application\UseCase\ScreenWorkHandler;
use Nexus\Screening\Domain\CouncilDecisionAggregator;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;
use Nexus\Screening\Domain\ScreeningVote;
use Nexus\Screening\Domain\ScreeningWork;

it('screens one work with a single model and records the auditable decision and vote', function (): void {
    $llm = new ScreeningFakeLlmClient([
        'openai/gpt-4.1-mini' => [
            'decision' => 'include',
            'confidence' => 0.93,
            'reason' => 'The title and abstract directly match tomato instance segmentation with limited labels.',
            'evidence' => ['tomato instance segmentation', 'limited labels'],
            'uncertainty' => [],
            'exclusion_basis' => [],
        ],
    ]);
    $decisions = new InMemoryScreeningDecisionRepository;
    $votes = new InMemoryScreeningVoteRepository;

    $handler = new ScreenWorkHandler(
        $llm,
        new FixedScreeningPromptRenderer,
        new CouncilDecisionAggregator,
        $decisions,
        $votes,
    );

    $verdict = $handler->handle(new ScreenWorkCommand(
        screeningRunId: 'run-1',
        projectId: 'project-1',
        work: tomatoWork(),
        criteria: tomatoCriteria(),
        stage: ScreeningStage::TITLE_ABSTRACT,
        model: 'openai/gpt-4.1-mini',
    ));

    expect($verdict->decision)->toBe(ScreeningDecision::INCLUDE)
        ->and($verdict->criteriaHash)->toBe(tomatoCriteria()->hash())
        ->and($decisions->recorded[0]->id)->toBe($verdict->id)
        ->and($votes->recorded)->toHaveCount(1)
        ->and($votes->recorded[0]->screeningDecisionId)->toBe($verdict->id)
        ->and($votes->recorded[0]->promptHash)->toBe('prompt-hash')
        ->and($votes->recorded[0]->prompt)->toBeNull()
        ->and($votes->recorded[0]->rawResponse)->toBeNull()
        ->and($llm->requests[0]->model)->toBe('openai/gpt-4.1-mini');
});

it('runs independent council model votes and preserves severe disagreement as needs review', function (): void {
    $llm = new ScreeningFakeLlmClient([
        'openai/gpt-4.1-mini' => [
            'decision' => 'include',
            'confidence' => 0.95,
            'reason' => 'Direct tomato instance segmentation match.',
            'evidence' => ['tomato instance segmentation'],
            'uncertainty' => [],
            'exclusion_basis' => [],
        ],
        'google/gemini-2.5-flash' => [
            'decision' => 'include',
            'confidence' => 0.91,
            'reason' => 'Limited-label tomato segmentation is central.',
            'evidence' => ['limited-label tomato segmentation'],
            'uncertainty' => [],
            'exclusion_basis' => [],
        ],
        'anthropic/claude-3.5-haiku' => [
            'decision' => 'exclude',
            'confidence' => 0.88,
            'reason' => 'The model judged the paper as classification-only.',
            'evidence' => ['classification'],
            'uncertainty' => [],
            'exclusion_basis' => ['classification only'],
        ],
    ]);
    $decisions = new InMemoryScreeningDecisionRepository;
    $votes = new InMemoryScreeningVoteRepository;

    $handler = new ScreenWorkHandler(
        $llm,
        new FixedScreeningPromptRenderer,
        new CouncilDecisionAggregator,
        $decisions,
        $votes,
    );

    $verdict = $handler->handle(new ScreenWorkCommand(
        screeningRunId: 'run-1',
        projectId: 'project-1',
        work: tomatoWork(),
        criteria: tomatoCriteria(),
        stage: ScreeningStage::TITLE_ABSTRACT,
        councilModels: [
            'openai/gpt-4.1-mini',
            'google/gemini-2.5-flash',
            'anthropic/claude-3.5-haiku',
        ],
    ));

    expect($verdict->decision)->toBe(ScreeningDecision::NEEDS_REVIEW)
        ->and($verdict->rationale->uncertainty)->toContain('council_include_exclude_conflict')
        ->and($votes->recorded)->toHaveCount(3)
        ->and(array_map(static fn (LlmRequest $request): string => $request->model, $llm->requests))->toBe([
            'openai/gpt-4.1-mini',
            'google/gemini-2.5-flash',
            'anthropic/claude-3.5-haiku',
        ]);
});

function tomatoCriteria(): ScreeningCriteria
{
    return ScreeningCriteria::fromArray([
        'include' => ['Tomato instance segmentation, label-efficient learning, crop vision.'],
        'exclude' => ['Medical imaging, animal datasets, classification-only plant papers.'],
    ]);
}

function tomatoWork(): ScreeningWork
{
    return new ScreeningWork(
        id: 'work-1',
        title: 'Label efficient tomato instance segmentation with weak annotations',
        abstract: 'We study tomato instance segmentation with limited pixel annotations.',
        year: 2025,
        venue: 'Plant Methods',
        sourceProvider: 'openalex',
        identifiers: ['doi' => '10.1000/tomato'],
    );
}

final class FixedScreeningPromptRenderer implements ScreeningPromptRendererPort
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
                'properties' => [
                    'decision' => ['type' => 'string'],
                ],
                'required' => ['decision'],
            ],
            hash: 'prompt-hash',
        );
    }
}

final class ScreeningFakeLlmClient implements LlmClientPort
{
    /** @var list<LlmRequest> */
    public array $requests = [];

    /**
     * @param  array<string, array<string, mixed>>  $responsesByModel
     */
    public function __construct(private readonly array $responsesByModel) {}

    public function completeJson(LlmRequest $request): LlmResponse
    {
        $this->requests[] = $request;
        $content = $this->responsesByModel[$request->model]
            ?? throw new RuntimeException("No fake response for {$request->model}");

        return new LlmResponse(
            provider: 'openrouter',
            model: $request->model,
            content: $content,
            rawResponse: json_encode(['choices' => [['message' => ['content' => $content]]]], JSON_THROW_ON_ERROR),
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 50],
            latencyMs: 25,
        );
    }
}

final class InMemoryScreeningDecisionRepository implements ScreeningDecisionRepositoryPort
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

    public function forRun(string $screeningRunId): array
    {
        return array_values(array_filter(
            $this->recorded,
            static fn (ScreeningVerdict $verdict): bool => $verdict->screeningRunId === $screeningRunId,
        ));
    }
}

final class InMemoryScreeningVoteRepository implements ScreeningVoteRepositoryPort
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
