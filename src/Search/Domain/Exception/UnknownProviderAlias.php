<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Exception;

use Nexus\Shared\Contract\DomainException;

final class UnknownProviderAlias extends DomainException
{
    /**
     * @param string[] $unknownAliases
     * @param string[] $availableAliases
     */
    public function __construct(
        public readonly array $unknownAliases,
        public readonly array $availableAliases,
    ) {
        parent::__construct(sprintf(
            'Unknown selected provider alias%s: %s. Available provider aliases: %s.',
            count($unknownAliases) === 1 ? '' : 'es',
            implode(', ', $unknownAliases),
            $availableAliases === [] ? 'none' : implode(', ', $availableAliases),
        ));
    }
}
