<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain\Port;

use Nexus\CitationNetwork\Domain\Exception\UnknownSnowballingProviderAlias;

final class SnowballingProviderCollection
{
    /** @var list<SnowballingProviderPort> */
    private array $providers;

    public function __construct(SnowballingProviderPort ...$providers)
    {
        $this->providers = array_values($providers);
    }

    /**
     * @return list<SnowballingProviderPort>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @param list<string> $aliases Empty means all registered providers.
     * @return list<SnowballingProviderPort>
     */
    public function matching(array $aliases): array
    {
        if ($aliases === []) {
            return $this->providers;
        }

        $available = $this->availableAliases();
        $unknown = array_values(array_diff($aliases, $available));

        if ($unknown !== []) {
            throw new UnknownSnowballingProviderAlias($unknown, $available);
        }

        $wanted = array_fill_keys($aliases, true);

        return array_values(array_filter(
            $this->providers,
            static fn (SnowballingProviderPort $provider): bool => isset($wanted[$provider->alias()]),
        ));
    }

    public function count(): int
    {
        return count($this->providers);
    }

    /**
     * @return list<string>
     */
    private function availableAliases(): array
    {
        return array_map(
            static fn (SnowballingProviderPort $provider): string => $provider->alias(),
            $this->providers,
        );
    }
}
