<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain;

enum CitationGraphType: string
{
    case CITATION               = 'citation';
    case CO_CITATION            = 'co_citation';
    case BIBLIOGRAPHIC_COUPLING = 'bibliographic_coupling';
}
