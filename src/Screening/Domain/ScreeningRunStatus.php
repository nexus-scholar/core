<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

enum ScreeningRunStatus: string
{
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
