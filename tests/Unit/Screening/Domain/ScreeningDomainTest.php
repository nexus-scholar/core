<?php

declare(strict_types=1);

use Nexus\Screening\Domain\CouncilDecisionAggregator;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVote;

it('uses tri-state decisions with safe included semantics', function (): void {
    expect(ScreeningDecision::INCLUDE->included())->toBeTrue()
        ->and(ScreeningDecision::NEEDS_REVIEW->included())->toBeFalse()
        ->and(ScreeningDecision::EXCLUDE->included())->toBeFalse();
});

it('creates stable criteria hashes from semantically equivalent arrays', function (): void {
    $criteriaA = ScreeningCriteria::fromArray([
        'exclude' => ['keywords' => ['medical', 'animal']],
        'include' => ['keywords' => ['tomato', 'instance segmentation']],
    ]);

    $criteriaB = ScreeningCriteria::fromArray([
        'include' => ['keywords' => ['tomato', 'instance segmentation']],
        'exclude' => ['keywords' => ['medical', 'animal']],
    ]);

    expect($criteriaA->hash())->toBe($criteriaB->hash())
        ->and($criteriaA->toArray())->toBe($criteriaB->toArray());
});

it('aggregates unanimous council votes into the shared decision', function (): void {
    $verdict = (new CouncilDecisionAggregator)->aggregate(
        projectId: 'project-1',
        workId: 'work-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        votes: [
            modelVote('openai/gpt-4.1-mini', ScreeningDecision::INCLUDE, 0.90),
            modelVote('google/gemini-2.5-flash', ScreeningDecision::INCLUDE, 0.84),
            modelVote('anthropic/claude-3.5-haiku', ScreeningDecision::INCLUDE, 0.87),
        ],
    );

    expect($verdict->decision)->toBe(ScreeningDecision::INCLUDE)
        ->and($verdict->included())->toBeTrue()
        ->and($verdict->confidence)->toBeGreaterThan(0.86)
        ->and($verdict->source)->toBe('llm_council')
        ->and($verdict->rationale->reason)->toContain('3 of 3');
});

it('uses conservative majority when no severe include exclude conflict exists', function (): void {
    $verdict = (new CouncilDecisionAggregator)->aggregate(
        projectId: 'project-1',
        workId: 'work-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        votes: [
            modelVote('openai/gpt-4.1-mini', ScreeningDecision::EXCLUDE, 0.91),
            modelVote('google/gemini-2.5-flash', ScreeningDecision::EXCLUDE, 0.86),
            modelVote('anthropic/claude-3.5-haiku', ScreeningDecision::NEEDS_REVIEW, 0.55),
        ],
    );

    expect($verdict->decision)->toBe(ScreeningDecision::EXCLUDE)
        ->and($verdict->included())->toBeFalse()
        ->and($verdict->rationale->reason)->toContain('2 of 3');
});

it('routes severe include exclude council conflicts to needs review', function (): void {
    $verdict = (new CouncilDecisionAggregator)->aggregate(
        projectId: 'project-1',
        workId: 'work-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        votes: [
            modelVote('openai/gpt-4.1-mini', ScreeningDecision::INCLUDE, 0.94),
            modelVote('google/gemini-2.5-flash', ScreeningDecision::INCLUDE, 0.91),
            modelVote('anthropic/claude-3.5-haiku', ScreeningDecision::EXCLUDE, 0.89),
        ],
    );

    expect($verdict->decision)->toBe(ScreeningDecision::NEEDS_REVIEW)
        ->and($verdict->included())->toBeFalse()
        ->and($verdict->rationale->uncertainty)->toContain('council_include_exclude_conflict');
});

it('keeps failed model attempts in the council audit path', function (): void {
    $verdict = (new CouncilDecisionAggregator)->aggregate(
        projectId: 'project-1',
        workId: 'work-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        votes: [
            modelVote('openai/gpt-4.1-mini', ScreeningDecision::INCLUDE, 0.92),
            modelVote('google/gemini-2.5-flash', ScreeningDecision::INCLUDE, 0.88),
            ScreeningVote::failed(
                screeningRunId: 'run-1',
                projectId: 'project-1',
                workId: 'work-1',
                stage: ScreeningStage::TITLE_ABSTRACT,
                provider: 'openrouter',
                model: 'anthropic/claude-3.5-haiku',
                attempt: 1,
                error: 'timeout',
            ),
        ],
    );

    expect($verdict->decision)->toBe(ScreeningDecision::INCLUDE)
        ->and($verdict->confidence)->toBeLessThan(0.90)
        ->and($verdict->rationale->uncertainty)->toContain('model_failure:anthropic/claude-3.5-haiku');
});

function modelVote(string $model, ScreeningDecision $decision, float $confidence): ScreeningVote
{
    return ScreeningVote::model(
        screeningRunId: 'run-1',
        projectId: 'project-1',
        workId: 'work-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        provider: 'openrouter',
        model: $model,
        attempt: 1,
        decision: $decision,
        confidence: $confidence,
        rationale: new ScreeningRationale(
            reason: "Model {$model} voted {$decision->value}.",
            evidence: ['title evidence'],
        ),
    );
}
