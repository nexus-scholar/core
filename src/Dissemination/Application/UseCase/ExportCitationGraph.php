<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\Dissemination\Domain\NetworkExportFormat;

final readonly class ExportCitationGraph
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public CitationGraph       $graph,
        public NetworkExportFormat $format,
        public string              $filename,
        public ?string             $requestedBy = null,
        public array               $metadata = [],
    ) {}
}
