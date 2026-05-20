<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use Mbsoft\Graph\IO\CytoscapeJsonExporter;
use Mbsoft\Graph\IO\GexfExporter;
use Mbsoft\Graph\IO\GraphMLExporter;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Infrastructure\Graph\MbsoftCitationGraphMapper;
use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Dissemination\Domain\Port\CitationGraphSerializerPort;

final readonly class MbsoftCitationGraphSerializer implements CitationGraphSerializerPort
{
    public function __construct(
        private MbsoftCitationGraphMapper $mapper = new MbsoftCitationGraphMapper(),
    ) {}

    public function serialize(CitationGraph $graph, NetworkExportFormat $format): string
    {
        $mapped = $this->mapper->toGraph($graph);

        return match ($format) {
            NetworkExportFormat::CYTOSCAPE => json_encode(
                (new CytoscapeJsonExporter())->export($mapped),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            NetworkExportFormat::GRAPHML => (new GraphMLExporter())->export($mapped),
            NetworkExportFormat::GEXF => (new GexfExporter())->export($mapped),
        };
    }

    public function supports(NetworkExportFormat $format): bool
    {
        return in_array($format, NetworkExportFormat::cases(), true);
    }
}
