<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain;

/**
 * Value object representing a unique identifier for a CitationGraph.
 */
final class CitationGraphId
{
    public function __construct(public readonly string $value)
    {
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(8)));
    }

    public function equals(CitationGraphId $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
