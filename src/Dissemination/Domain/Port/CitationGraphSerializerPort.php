<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\Dissemination\Domain\NetworkExportFormat;

interface CitationGraphSerializerPort
{
    public function serialize(CitationGraph $graph, NetworkExportFormat $format): string;

    public function supports(NetworkExportFormat $format): bool;
}
