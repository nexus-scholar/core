<?php

declare(strict_types=1);

namespace Nexus\Search\Application;

use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\ScholarlyWork;

/**
 * Tries each provider that supports the ID namespace in registration order.
 * Returns the first successful result, or null if no provider finds the work.
 */
final class SearchByWorkIdHandler
{
    public function __construct(
        /** @var AcademicProviderPort[] */
        private readonly array $providers,
    ) {}

    public function handle(SearchByWorkId $command): ?ScholarlyWork
    {
        $eligibleProviders = array_filter(
            $this->providers,
            fn (AcademicProviderPort $p) => $p->supports($command->id->namespace)
                && ($command->providerAliases === [] || in_array($p->alias(), $command->providerAliases, true)),
        );

        foreach ($eligibleProviders as $provider) {
            $work = $provider->fetchById($command->id);

            if ($work !== null) {
                return $work;
            }
        }

        return null;
    }
}
