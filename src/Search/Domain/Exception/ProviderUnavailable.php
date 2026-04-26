<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Exception;

use Nexus\Shared\Contract\DomainException;

final class ProviderUnavailable extends DomainException
{
    public function __construct(
        public readonly string $providerAlias,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Provider \"{$providerAlias}\" is unavailable: {$reason}",
            0,
            $previous
        );
    }
}
