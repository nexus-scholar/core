<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Domain\ScreeningDecision;

final readonly class HumanAdjudicationInput
{
    /**
     * @param  list<string>  $evidence
     * @param  list<string>  $uncertainty
     * @param  list<string>  $exclusionBasis
     * @param  list<string>  $sourceDecisionIds
     */
    public function __construct(
        public string $workId,
        public ScreeningDecision $decision,
        public string $reason,
        public array $evidence = [],
        public array $uncertainty = [],
        public array $exclusionBasis = [],
        public array $sourceDecisionIds = [],
        public float $confidence = 1.0,
    ) {
        if (trim($workId) === '') {
            throw new \InvalidArgumentException('Human adjudication input requires a work id.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Human adjudication input requires a rationale.');
        }

        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException('Human adjudication confidence must be between 0 and 1.');
        }
    }
}
