<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Llm;

final readonly class LlmResponse
{
    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $usage
     */
    public function __construct(
        public string $provider,
        public string $model,
        public array $content,
        public ?string $rawResponse = null,
        public array $usage = [],
        public ?int $latencyMs = null,
    ) {}

    public function responseHash(): ?string
    {
        if ($this->rawResponse === null || trim($this->rawResponse) === '') {
            return null;
        }

        return hash('sha256', $this->rawResponse);
    }
}
