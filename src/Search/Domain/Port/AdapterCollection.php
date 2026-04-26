<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

final class AdapterCollection
{
    /** @var AcademicProviderPort[] */
    private array $adapters;

    public function __construct(AcademicProviderPort ...$adapters)
    {
        $this->adapters = $adapters;
    }

    /** @return AcademicProviderPort[] */
    public function all(): array
    {
        return $this->adapters;
    }

    public function count(): int
    {
        return count($this->adapters);
    }
}
