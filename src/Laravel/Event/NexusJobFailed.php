<?php

declare(strict_types=1);

namespace Nexus\Laravel\Event;

use DateTimeImmutable;

final readonly class NexusJobFailed
{
    public DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $runId,
        public string $jobName,
        public string $jobClass,
        public array $context,
        public string $errorClass,
        public string $errorMessage,
        public int $durationMs = 0,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }
}
