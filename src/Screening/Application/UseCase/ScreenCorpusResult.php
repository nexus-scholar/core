<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Domain\ScreeningVerdict;

final readonly class ScreenCorpusResult
{
    /**
     * @param  list<ScreeningVerdict>  $verdicts
     * @param  list<array{work_id: string, error: string}>  $failures
     */
    public function __construct(
        public string $runId,
        public int $total,
        public int $included,
        public int $needsReview,
        public int $excluded,
        public int $failed,
        public int $durationMs,
        public array $verdicts = [],
        public array $failures = [],
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

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
            'failed' => $this->failed,
        ];
    }
}
