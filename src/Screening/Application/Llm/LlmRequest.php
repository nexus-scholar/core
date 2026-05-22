<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Llm;

final readonly class LlmRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $responseSchema
     */
    public function __construct(
        public string $model,
        public array $messages,
        public array $responseSchema,
        public float $temperature = 0.0,
        public int $maxTokens = 600,
    ) {
        if (trim($model) === '') {
            throw new \InvalidArgumentException('LLM model must not be empty.');
        }

        if ($messages === []) {
            throw new \InvalidArgumentException('LLM request messages must not be empty.');
        }

        if ($maxTokens < 1) {
            throw new \InvalidArgumentException('LLM request max tokens must be greater than zero.');
        }
    }
}
