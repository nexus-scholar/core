<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain;

final class DedupClusterId
{
    public function __construct(public readonly string $value) {}

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(8)));
    }

    public function equals(DedupClusterId $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
