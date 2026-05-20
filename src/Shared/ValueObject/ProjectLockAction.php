<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

enum ProjectLockAction: string
{
    case LOCKED = 'locked';
    case UNLOCKED = 'unlocked';
}
