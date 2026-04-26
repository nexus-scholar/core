<?php

declare(strict_types=1);

namespace Nexus\Search\Domain;

use Nexus\Search\Domain\Exception\InvalidSearchTerm;

final class SearchTerm
{
    public readonly string $value;

    public function __construct(string $value)
    {
        if (mb_strlen(trim($value)) < 2) {
            throw new InvalidSearchTerm(
                "Search term must have at least 2 non-whitespace characters; got \"{$value}\"."
            );
        }

        $this->value = $value;
    }

    public function equals(SearchTerm $other): bool
    {
        return $this->value === $other->value;
    }
}
