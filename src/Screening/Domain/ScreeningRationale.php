<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

final readonly class ScreeningRationale
{
    /**
     * @param  list<string>  $evidence
     * @param  list<string>  $uncertainty
     * @param  list<string>  $exclusionBasis
     */
    public function __construct(
        public string $reason,
        public array $evidence = [],
        public array $uncertainty = [],
        public array $exclusionBasis = [],
    ) {}

    /**
     * @return array{reason: string, evidence: list<string>, uncertainty: list<string>, exclusion_basis: list<string>}
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason,
            'evidence' => array_values(array_unique($this->evidence)),
            'uncertainty' => array_values(array_unique($this->uncertainty)),
            'exclusion_basis' => array_values(array_unique($this->exclusionBasis)),
        ];
    }
}
