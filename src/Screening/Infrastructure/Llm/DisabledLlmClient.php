<?php

declare(strict_types=1);

namespace Nexus\Screening\Infrastructure\Llm;

use Nexus\Screening\Application\Llm\LlmRequest;
use Nexus\Screening\Application\Llm\LlmResponse;
use Nexus\Screening\Application\Port\LlmClientPort;

final readonly class DisabledLlmClient implements LlmClientPort
{
    public function __construct(private string $reason = 'LLM screening is not configured.') {}

    public function completeJson(LlmRequest $request): LlmResponse
    {
        throw new LlmClientException($this->reason, model: $request->model);
    }
}
