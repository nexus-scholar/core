<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Domain\ScreeningStage;

final readonly class CompareScreeningRunsCommand
{
    public function __construct(
        public string $projectId,
        public string $baselineRunId,
        public string $candidateRunId,
        public ?ScreeningStage $stage = null,
        public bool $includeRows = true,
    ) {
        if (trim($projectId) === '') {
            throw new \InvalidArgumentException('Screening run comparison requires a project id.');
        }

        if (trim($baselineRunId) === '' || trim($candidateRunId) === '') {
            throw new \InvalidArgumentException('Screening run comparison requires both run ids.');
        }

        if ($baselineRunId === $candidateRunId) {
            throw new \InvalidArgumentException('Screening run comparison requires two different runs.');
        }
    }
}
