<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

final readonly class ScreeningRun
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $source
     * @param  array<string, int>  $counts
     */
    public function __construct(
        public string $id,
        public string $projectId,
        public ScreeningStage $stage,
        public ScreeningRunMode $mode,
        public ScreeningRunStatus $status,
        public ScreeningCriteria $criteria,
        public ?string $name = null,
        public array $config = [],
        public array $source = [],
        public array $counts = [],
        public ?\DateTimeImmutable $startedAt = null,
        public ?\DateTimeImmutable $completedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $source
     */
    public static function start(
        string $id,
        string $projectId,
        ScreeningStage $stage,
        ScreeningRunMode $mode,
        ScreeningCriteria $criteria,
        ?string $name = null,
        array $config = [],
        array $source = [],
    ): self {
        return new self(
            id: $id,
            projectId: $projectId,
            stage: $stage,
            mode: $mode,
            status: ScreeningRunStatus::RUNNING,
            criteria: $criteria,
            name: $name,
            config: $config,
            source: $source,
            startedAt: new \DateTimeImmutable,
        );
    }
}
