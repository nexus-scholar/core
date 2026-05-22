<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningWork;

final readonly class ScreenWorkCommand
{
    /**
     * @param  list<string>  $councilModels
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $screeningRunId,
        public string $projectId,
        public ScreeningWork $work,
        public ScreeningCriteria $criteria,
        public ScreeningStage $stage = ScreeningStage::TITLE_ABSTRACT,
        public ?string $model = null,
        public array $councilModels = [],
        public ?ScreeningRunMode $mode = null,
        public string $provider = 'openrouter',
        public array $context = [],
        public float $temperature = 0.0,
        public int $maxTokens = 600,
        public bool $storePrompt = false,
        public bool $storeRawResponse = false,
    ) {
        if (trim($screeningRunId) === '') {
            throw new \InvalidArgumentException('Screen work command requires a screening run id.');
        }

        if (trim($projectId) === '') {
            throw new \InvalidArgumentException('Screen work command requires a project id.');
        }
    }

    public function mode(): ScreeningRunMode
    {
        return $this->mode ?? ($this->councilModels === []
            ? ScreeningRunMode::LLM_SINGLE
            : ScreeningRunMode::LLM_COUNCIL);
    }

    /**
     * @return non-empty-list<string>
     */
    public function models(): array
    {
        $models = $this->councilModels === []
            ? [$this->model ?? 'openai/gpt-4.1-mini']
            : $this->councilModels;

        $models = array_values(array_unique(array_filter(
            array_map(static fn (string $model): string => trim($model), $models),
            static fn (string $model): bool => $model !== '',
        )));

        if ($models === []) {
            throw new \InvalidArgumentException('Screen work command requires at least one LLM model.');
        }

        return $models;
    }
}
