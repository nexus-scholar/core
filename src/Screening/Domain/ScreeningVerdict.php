<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

final readonly class ScreeningVerdict
{
    /**
     * @param  list<ScreeningVote>  $votes
     */
    public function __construct(
        public string $id,
        public ?string $screeningRunId,
        public string $projectId,
        public string $workId,
        public ScreeningStage $stage,
        public ScreeningDecision $decision,
        public ?float $confidence,
        public string $source,
        public ScreeningRationale $rationale,
        public ?string $decidedBy = null,
        public ?\DateTimeImmutable $decidedAt = null,
        public ?string $criteriaHash = null,
        public array $votes = [],
    ) {
        if ($confidence !== null && ($confidence < 0.0 || $confidence > 1.0)) {
            throw new \InvalidArgumentException('Screening confidence must be between 0 and 1.');
        }
    }

    public function included(): bool
    {
        return $this->decision->included();
    }
}
