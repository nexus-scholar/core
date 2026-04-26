<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Event;

use Nexus\Shared\Contract\DomainEvent;

final class SearchQueryExecuted implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $queryId,
        public readonly string $providerAlias,
        public readonly int    $resultCount,
        public readonly int    $durationMs,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'search.query.executed';
    }
}
