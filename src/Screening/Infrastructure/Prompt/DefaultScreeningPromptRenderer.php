<?php

declare(strict_types=1);

namespace Nexus\Screening\Infrastructure\Prompt;

use Nexus\Screening\Application\Port\ScreeningPromptRendererPort;
use Nexus\Screening\Application\Prompt\ScreeningPrompt;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningWork;

final class DefaultScreeningPromptRenderer implements ScreeningPromptRendererPort
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function render(
        ScreeningWork $work,
        ScreeningCriteria $criteria,
        ScreeningStage $stage,
        array $context = [],
    ): ScreeningPrompt {
        $messages = [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You are screening scientific literature for a systematic review.',
                    'Use only the title and abstract supplied in the user message.',
                    'Return the required JSON object only.',
                    'Do not include hidden reasoning or chain-of-thought.',
                    'If the abstract is missing and the title is not decisive, choose needs_review.',
                    'If the paper is clearly outside scope, choose exclude.',
                    'If the paper is a review, survey, background paper, or transferable non-target method, choose needs_review unless criteria explicitly include it.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => $this->userMessage($work, $criteria, $stage, $context),
            ],
        ];

        return new ScreeningPrompt(
            messages: $messages,
            responseSchema: $this->responseSchema(),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function userMessage(
        ScreeningWork $work,
        ScreeningCriteria $criteria,
        ScreeningStage $stage,
        array $context,
    ): string {
        return implode("\n", [
            'Project: '.$this->stringValue($context['project'] ?? 'unspecified'),
            'Stage: '.$stage->value,
            'Criteria JSON: '.json_encode($criteria->toArray(), JSON_THROW_ON_ERROR),
            'Work id: '.$work->id,
            'Title: '.$work->title,
            'Abstract missing: '.($work->hasAbstract() ? 'no' : 'yes'),
            'Abstract: '.($work->abstract ?? ''),
            'Year: '.($work->year === null ? 'unknown' : (string) $work->year),
            'Venue: '.($work->venue ?? 'unknown'),
            'Provider: '.($work->sourceProvider ?? 'unknown'),
            'Identifiers JSON: '.json_encode($work->identifiers, JSON_THROW_ON_ERROR),
        ]);
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return trim((string) $value) === '' ? 'unspecified' : (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'decision' => [
                    'type' => 'string',
                    'enum' => ['include', 'needs_review', 'exclude'],
                ],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
                'reason' => [
                    'type' => 'string',
                ],
                'evidence' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'uncertainty' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'exclusion_basis' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => [
                'decision',
                'confidence',
                'reason',
                'evidence',
                'uncertainty',
                'exclusion_basis',
            ],
            'additionalProperties' => false,
        ];
    }
}
