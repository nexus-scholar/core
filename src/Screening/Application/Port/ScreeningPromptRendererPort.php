<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Port;

use Nexus\Screening\Application\Prompt\ScreeningPrompt;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningWork;

interface ScreeningPromptRendererPort
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function render(
        ScreeningWork $work,
        ScreeningCriteria $criteria,
        ScreeningStage $stage,
        array $context = [],
    ): ScreeningPrompt;
}
