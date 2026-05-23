<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Event;

use Nexus\Shared\Contract\DomainEvent;
use Nexus\Shared\Domain\CorpusSlice;

final class ProviderSearchCompleted implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $queryId,
        public readonly string $providerAlias,
        public readonly CorpusSlice $slice,
    ) {
        $this->occurredAt = new \DateTimeImmutable;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'search.provider.completed';
    }
}
