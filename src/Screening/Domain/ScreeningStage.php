<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

enum ScreeningStage: string
{
    case TITLE_ABSTRACT = 'title_abstract';
    case FULL_TEXT = 'full_text';
    case HUMAN_ADJUDICATION = 'human_adjudication';
}
