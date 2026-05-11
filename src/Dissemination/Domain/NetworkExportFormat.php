<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

enum NetworkExportFormat: string
{
    case GEXF    = 'gexf';
    case GRAPHML = 'graphml';
    case CYTOSCAPE = 'cytoscape';
}
