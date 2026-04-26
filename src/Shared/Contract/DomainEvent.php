<?php

declare(strict_types=1);

namespace Nexus\Shared\Contract;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;

    /** e.g. "search.query.executed" */
    public function eventName(): string;
}
