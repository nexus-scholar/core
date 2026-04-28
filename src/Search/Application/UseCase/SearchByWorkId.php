<?php

declare(strict_types=1);

namespace Nexus\Search\Application\UseCase;

use Nexus\Shared\ValueObject\WorkId;

/**
 * Command to search for a specific work by its identifier (DOI, arXiv, etc).
 */
final class SearchByWorkId
{
    public function __construct(
        public readonly WorkId $id,
        public readonly array  $providerAliases = [],
    ) {}
}
