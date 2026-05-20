<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Plan;

use RuntimeException;

final class SearchPlanException extends RuntimeException
{
    public static function invalid(string $message): self
    {
        return new self($message);
    }

    /**
     * @param list<string> $missingIds
     */
    public static function unknownIds(array $missingIds, string $sourceName): self
    {
        return new self(sprintf(
            'Search plan %s does not contain query ID%s: %s.',
            $sourceName,
            count($missingIds) === 1 ? '' : 's',
            implode(', ', $missingIds),
        ));
    }
}
