<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;

final readonly class ScreenCorpusCommand
{
    /**
     * @param  list<string>  $councilModels
     * @param  list<string>  $workIds
     * @param  list<string>  $queryIds
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $projectId,
        public ScreeningCriteria $criteria,
        public ?string $screeningRunId = null,
        public ScreeningStage $stage = ScreeningStage::TITLE_ABSTRACT,
        public ScreeningRunMode $mode = ScreeningRunMode::LLM_SINGLE,
        public ?string $model = null,
        public array $councilModels = [],
        public ?int $limit = null,
        public array $workIds = [],
        public array $queryIds = [],
        public ?string $name = null,
        public array $context = [],
        public float $temperature = 0.0,
        public int $maxTokens = 600,
        public bool $storePrompt = false,
        public bool $storeRawResponse = false,
        public bool $continueOnFailure = true,
    ) {
        if (trim($projectId) === '') {
            throw new \InvalidArgumentException('Screen corpus command requires a project id.');
        }

        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException('Screen corpus limit must be greater than zero.');
        }
    }

    public function runId(): string
    {
        return $this->screeningRunId ?? bin2hex(random_bytes(16));
    }

    /**
     * @return array<string, mixed>
     */
    public function runConfig(): array
    {
        return [
            'stage' => $this->stage->value,
            'mode' => $this->mode->value,
            'model' => $this->model,
            'council_models' => $this->councilModels,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'store_prompt' => $this->storePrompt,
            'store_raw_response' => $this->storeRawResponse,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runSource(): array
    {
        return [
            'project_id' => $this->projectId,
            'limit' => $this->limit,
            'work_ids' => $this->workIds,
            'query_ids' => $this->queryIds,
        ];
    }
}
