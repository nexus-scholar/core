<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

enum ExportType: string
{
    case BIBLIOGRAPHY = 'bibliography';
    case NETWORK = 'network';
}
