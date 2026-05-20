<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain\Exception;

use InvalidArgumentException;

final class UnknownSnowballingProviderAlias extends InvalidArgumentException
{
    /**
     * @param list<string> $unknown
     * @param list<string> $available
     */
    public function __construct(
        public readonly array $unknown,
        public readonly array $available,
    ) {
        parent::__construct(sprintf(
            'Unknown snowballing provider alias%s: %s. Available aliases: %s.',
            count($unknown) === 1 ? '' : 'es',
            implode(', ', $unknown),
            $available === [] ? '(none)' : implode(', ', $available),
        ));
    }
}
