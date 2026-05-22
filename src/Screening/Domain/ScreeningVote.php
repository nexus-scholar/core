<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

final readonly class ScreeningVote
{
    /**
     * @param  array<string, mixed>  $usage
     */
    private function __construct(
        public string $id,
        public string $screeningRunId,
        public ?string $screeningDecisionId,
        public string $projectId,
        public string $workId,
        public ScreeningStage $stage,
        public string $provider,
        public string $model,
        public int $attempt,
        public ?ScreeningDecision $decision,
        public ?float $confidence,
        public ScreeningRationale $rationale,
        public array $usage = [],
        public ?int $latencyMs = null,
        public ?string $error = null,
        public ?string $promptHash = null,
        public ?string $responseHash = null,
        public ?string $prompt = null,
        public ?string $rawResponse = null,
    ) {
        if ($attempt < 1) {
            throw new \InvalidArgumentException('Screening vote attempt must be greater than zero.');
        }

        if ($confidence !== null && ($confidence < 0.0 || $confidence > 1.0)) {
            throw new \InvalidArgumentException('Screening vote confidence must be between 0 and 1.');
        }
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    public static function model(
        string $screeningRunId,
        string $projectId,
        string $workId,
        ScreeningStage $stage,
        string $provider,
        string $model,
        int $attempt,
        ScreeningDecision $decision,
        float $confidence,
        ScreeningRationale $rationale,
        ?string $id = null,
        ?string $screeningDecisionId = null,
        array $usage = [],
        ?int $latencyMs = null,
        ?string $promptHash = null,
        ?string $responseHash = null,
        ?string $prompt = null,
        ?string $rawResponse = null,
    ): self {
        return new self(
            id: $id ?? self::generateId(),
            screeningRunId: $screeningRunId,
            screeningDecisionId: $screeningDecisionId,
            projectId: $projectId,
            workId: $workId,
            stage: $stage,
            provider: $provider,
            model: $model,
            attempt: $attempt,
            decision: $decision,
            confidence: $confidence,
            rationale: $rationale,
            usage: $usage,
            latencyMs: $latencyMs,
            promptHash: $promptHash,
            responseHash: $responseHash,
            prompt: $prompt,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    public static function failed(
        string $screeningRunId,
        string $projectId,
        string $workId,
        ScreeningStage $stage,
        string $provider,
        string $model,
        int $attempt,
        string $error,
        ?string $id = null,
        ?string $screeningDecisionId = null,
        ?int $latencyMs = null,
        array $usage = [],
        ?string $promptHash = null,
        ?string $responseHash = null,
        ?string $prompt = null,
        ?string $rawResponse = null,
    ): self {
        return new self(
            id: $id ?? self::generateId(),
            screeningRunId: $screeningRunId,
            screeningDecisionId: $screeningDecisionId,
            projectId: $projectId,
            workId: $workId,
            stage: $stage,
            provider: $provider,
            model: $model,
            attempt: $attempt,
            decision: null,
            confidence: null,
            rationale: new ScreeningRationale(
                reason: 'Model vote failed.',
                uncertainty: ["model_failure:{$model}"],
            ),
            usage: $usage,
            latencyMs: $latencyMs,
            error: $error,
            promptHash: $promptHash,
            responseHash: $responseHash,
            prompt: $prompt,
            rawResponse: $rawResponse,
        );
    }

    public function succeeded(): bool
    {
        return $this->decision instanceof ScreeningDecision;
    }

    public function withScreeningDecisionId(string $screeningDecisionId): self
    {
        return new self(
            id: $this->id,
            screeningRunId: $this->screeningRunId,
            screeningDecisionId: $screeningDecisionId,
            projectId: $this->projectId,
            workId: $this->workId,
            stage: $this->stage,
            provider: $this->provider,
            model: $this->model,
            attempt: $this->attempt,
            decision: $this->decision,
            confidence: $this->confidence,
            rationale: $this->rationale,
            usage: $this->usage,
            latencyMs: $this->latencyMs,
            error: $this->error,
            promptHash: $this->promptHash,
            responseHash: $this->responseHash,
            prompt: $this->prompt,
            rawResponse: $this->rawResponse,
        );
    }

    private static function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
