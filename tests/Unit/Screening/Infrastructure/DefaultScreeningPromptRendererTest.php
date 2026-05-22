<?php

declare(strict_types=1);

use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningWork;
use Nexus\Screening\Infrastructure\Prompt\DefaultScreeningPromptRenderer;

it('renders a stable schema-bound scientific screening prompt', function (): void {
    $renderer = new DefaultScreeningPromptRenderer;
    $work = new ScreeningWork(
        id: 'work-1',
        title: 'Transfer learning for crop instance segmentation',
        abstract: null,
        year: 2024,
        venue: 'Computers and Electronics in Agriculture',
        sourceProvider: 'crossref',
    );
    $criteria = ScreeningCriteria::fromArray([
        'include' => ['tomato or crop instance segmentation', 'label efficiency'],
        'exclude' => ['classification only'],
    ]);

    $prompt = $renderer->render(
        work: $work,
        criteria: $criteria,
        stage: ScreeningStage::TITLE_ABSTRACT,
        context: ['project' => 'TomatoMAP label efficiency'],
    );

    expect($prompt->messages)->toHaveCount(2)
        ->and($prompt->messages[0]['role'])->toBe('system')
        ->and($prompt->messages[1]['content'])->toContain('Transfer learning for crop instance segmentation')
        ->and($prompt->messages[1]['content'])->toContain('Abstract missing: yes')
        ->and($prompt->messages[1]['content'])->toContain('TomatoMAP label efficiency')
        ->and($prompt->responseSchema['properties']['decision']['enum'])->toBe(['include', 'needs_review', 'exclude'])
        ->and($prompt->hash)->toBe($renderer->render($work, $criteria, ScreeningStage::TITLE_ABSTRACT, [
            'project' => 'TomatoMAP label efficiency',
        ])->hash);
});
