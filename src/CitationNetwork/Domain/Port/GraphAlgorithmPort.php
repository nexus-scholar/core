<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain\Port;

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationPath;
use Nexus\CitationNetwork\Domain\NetworkMetrics;
use Nexus\Shared\ValueObject\WorkId;

interface GraphAlgorithmPort
{
    public function compute(CitationGraph $graph): NetworkMetrics;

    public function shortestPath(CitationGraph $graph, WorkId $source, WorkId $target): ?CitationPath;
}
