<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Domain\CitationGraphId;
use Nexus\Shared\ValueObject\WorkId;

final readonly class FindShortestCitationPath
{
    public function __construct(
        public CitationGraphId $graphId,
        public WorkId $source,
        public WorkId $target,
    ) {
    }
}
