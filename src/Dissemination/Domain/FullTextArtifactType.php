<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

enum FullTextArtifactType: string
{
    case PDF = 'pdf';
    case XML = 'xml';
    case TEXT = 'text';
}
