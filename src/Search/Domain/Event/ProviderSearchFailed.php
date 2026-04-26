<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Event;

use Nexus\Shared\Contract\DomainEvent;

final class ProviderSearchFailed implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $queryId,
        public readonly string $providerAlias,
        public readonly string $reason,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'search.provider.failed';
    }
}
