<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

final readonly class ScreeningRunComparisonRow
{
    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $candidate
     */
    public function __construct(
        public string $workId,
        public ?string $baselineDecision,
        public ?string $candidateDecision,
        public bool $changed,
        public array $baseline = [],
        public array $candidate = [],
    ) {}
}
