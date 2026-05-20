<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Domain\CitationGraphId;

final readonly class AnalyzeNetwork
{
    public function __construct(
        public CitationGraphId $graphId,
        public bool $persistMetrics = true,
    ) {
    }
}
