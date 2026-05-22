<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

final readonly class ScreeningRunComparisonResult
{
    /**
     * @param  array<string, mixed>  $baselineRun
     * @param  array<string, mixed>  $candidateRun
     * @param  array<string, array<string, int>>  $transitionCounts
     * @param  list<string>  $missingInBaseline
     * @param  list<string>  $missingInCandidate
     * @param  list<ScreeningRunComparisonRow>  $rows
     */
    public function __construct(
        public string $projectId,
        public array $baselineRun,
        public array $candidateRun,
        public int $comparableTotal,
        public int $agreementCount,
        public int $disagreementCount,
        public float $agreementRate,
        public float $disagreementRate,
        public array $transitionCounts,
        public array $missingInBaseline,
        public array $missingInCandidate,
        public array $rows,
        public ?string $referenceRunId = null,
    ) {}
}
