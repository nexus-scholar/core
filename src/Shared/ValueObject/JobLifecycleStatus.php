<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

enum JobLifecycleStatus: string
{
    case STARTED = 'started';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
