<?php

declare(strict_types=1);

namespace Nexus\Screening\Infrastructure\Llm;

final class LlmClientException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider = 'unknown',
        public readonly ?string $model = null,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
