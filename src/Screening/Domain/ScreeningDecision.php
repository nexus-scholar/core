<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

enum ScreeningDecision: string
{
    case INCLUDE = 'include';
    case NEEDS_REVIEW = 'needs_review';
    case EXCLUDE = 'exclude';

    public function included(): bool
    {
        return $this === self::INCLUDE;
    }
}
