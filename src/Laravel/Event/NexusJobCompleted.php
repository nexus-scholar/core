<?php

declare(strict_types=1);

namespace Nexus\Laravel\Event;

use DateTimeImmutable;

final readonly class NexusJobCompleted
{
    public DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $summary
     */
    public function __construct(
        public string $runId,
        public string $jobName,
        public string $jobClass,
        public array $context = [],
        public array $summary = [],
        public int $durationMs = 0,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }
}
