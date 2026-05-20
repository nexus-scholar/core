<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain;

enum SnowballDirection: string
{
    case FORWARD = 'forward';
    case BACKWARD = 'backward';
}
