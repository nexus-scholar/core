<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

enum JobLifecycleStatus: string
{
    case STARTED = 'started';
    case PROGRESSED = 'progressed';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
