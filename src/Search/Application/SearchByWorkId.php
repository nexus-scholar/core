<?php

declare(strict_types=1);

namespace Nexus\Search\Application;

use Nexus\Shared\ValueObject\WorkId;

final class SearchByWorkId
{
    public function __construct(
        public readonly WorkId $id,
        public readonly array  $providerAliases = [],
    ) {}
}
