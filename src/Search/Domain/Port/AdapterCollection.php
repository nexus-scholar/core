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

    /**
     * @param string[] $aliases Empty means all registered adapters.
     * @return AcademicProviderPort[]
     */
    public function matching(array $aliases): array
    {
        if ($aliases === []) {
            return $this->adapters;
        }

        $wanted = array_fill_keys($aliases, true);

        return array_values(array_filter(
            $this->adapters,
            fn (AcademicProviderPort $adapter) => isset($wanted[$adapter->alias()]),
        ));
    }

    public function count(): int
    {
        return count($this->adapters);
    }
}
