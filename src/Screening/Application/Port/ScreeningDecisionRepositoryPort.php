<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Port;

use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;

interface ScreeningDecisionRepositoryPort
{
    public function record(ScreeningVerdict $verdict): void;

    public function latestForWork(string $projectId, string $workId, ScreeningStage $stage): ?ScreeningVerdict;
}
