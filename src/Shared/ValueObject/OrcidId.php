<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

final class OrcidId
{
    public readonly string $value;

    public function __construct(string $value)
    {
        if (! preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $value)) {
            throw new \InvalidArgumentException(
                "Invalid ORCID format: \"{$value}\". Expected \\d{4}-\\d{4}-\\d{4}-\\d{3}[\\dX]"
            );
        }

        $this->value = $value;
    }

    public function equals(OrcidId $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
