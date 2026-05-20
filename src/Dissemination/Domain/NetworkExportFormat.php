<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

enum NetworkExportFormat: string
{
    case GEXF    = 'gexf';
    case GRAPHML = 'graphml';
    case CYTOSCAPE = 'cytoscape';

    public function extension(): string
    {
        return match ($this) {
            self::GEXF => 'gexf',
            self::GRAPHML => 'graphml',
            self::CYTOSCAPE => 'json',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::GEXF,
            self::GRAPHML => 'application/xml',
            self::CYTOSCAPE => 'application/json',
        };
    }
}
