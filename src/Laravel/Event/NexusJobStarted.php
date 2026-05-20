<?php

declare(strict_types=1);

namespace Nexus\Laravel\Event;

use DateTimeImmutable;

final readonly class NexusJobStarted
{
    public DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $runId,
        public string $jobName,
        public string $jobClass,
        public array $context = [],
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }
}
