<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

enum FullTextStatus: string
{
    case SUCCESS = 'success';
    case FAILURE = 'failure';
    case SKIPPED = 'skipped';
}
