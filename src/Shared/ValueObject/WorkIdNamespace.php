<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

enum WorkIdNamespace: string
{
    case DOI      = 'doi';
    case ARXIV    = 'arxiv';
    case OPENALEX = 'openalex';
    case S2       = 's2';
    case PUBMED   = 'pubmed';
    case IEEE     = 'ieee';
    case DOAJ     = 'doaj';
    case INTERNAL = 'internal';
}
