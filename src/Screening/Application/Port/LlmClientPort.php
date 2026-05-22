<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Port;

use Nexus\Screening\Application\Llm\LlmRequest;
use Nexus\Screening\Application\Llm\LlmResponse;

interface LlmClientPort
{
    public function completeJson(LlmRequest $request): LlmResponse;
}
