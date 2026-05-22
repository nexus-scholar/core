<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

enum ScreeningRunMode: string
{
    case RULES = 'rules';
    case LLM_SINGLE = 'llm_single';
    case LLM_COUNCIL = 'llm_council';
    case HUMAN = 'human';
}
