<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningVerdict;

final readonly class AdjudicateScreeningDecisionsResult
{
    /**
     * @param  list<ScreeningVerdict>  $verdicts
     */
    public function __construct(
        public string $runId,
        public int $total,
        public int $included,
        public int $needsReview,
        public int $excluded,
        public array $verdicts,
    ) {}

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'total' => $this->total,
            'included' => $this->included,
            'needs_review' => $this->needsReview,
            'excluded' => $this->excluded,
            'failed' => 0,
        ];
    }

    /**
     * @param  list<ScreeningVerdict>  $verdicts
     */
    public static function fromVerdicts(string $runId, array $verdicts): self
    {
        $included = 0;
        $needsReview = 0;
        $excluded = 0;

        foreach ($verdicts as $verdict) {
            match ($verdict->decision) {
                ScreeningDecision::INCLUDE => $included++,
                ScreeningDecision::NEEDS_REVIEW => $needsReview++,
                ScreeningDecision::EXCLUDE => $excluded++,
            };
        }

        return new self(
            runId: $runId,
            total: count($verdicts),
            included: $included,
            needsReview: $needsReview,
            excluded: $excluded,
            verdicts: $verdicts,
        );
    }
}
