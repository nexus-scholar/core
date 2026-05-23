<?php

declare(strict_types=1);

namespace Nexus\Shared\Domain;

final class CorpusSliceId
{
    public function __construct(public readonly string $value) {}

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(8)));
    }

    public function equals(CorpusSliceId $other): bool
    {
        return $this->value === $other->value;
    }
}
