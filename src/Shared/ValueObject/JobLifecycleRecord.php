<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

use DateTimeImmutable;

final readonly class JobLifecycleRecord
{
    public DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $summary
     */
    public function __construct(
        public string $idempotencyKey,
        public string $runId,
        public string $jobName,
        public string $jobClass,
        public JobLifecycleStatus $status,
        public array $context = [],
        public array $summary = [],
        public ?string $errorClass = null,
        public ?string $errorMessage = null,
        public int $durationMs = 0,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function started(
        string $runId,
        string $jobName,
        string $jobClass,
        array $context,
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        return new self(
            idempotencyKey: self::key($runId, JobLifecycleStatus::STARTED),
            runId: $runId,
            jobName: $jobName,
            jobClass: $jobClass,
            status: JobLifecycleStatus::STARTED,
            context: $context,
            occurredAt: $occurredAt,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $summary
     */
    public static function completed(
        string $runId,
        string $jobName,
        string $jobClass,
        array $context,
        array $summary,
        int $durationMs,
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        return new self(
            idempotencyKey: self::key($runId, JobLifecycleStatus::COMPLETED),
            runId: $runId,
            jobName: $jobName,
            jobClass: $jobClass,
            status: JobLifecycleStatus::COMPLETED,
            context: $context,
            summary: $summary,
            durationMs: $durationMs,
            occurredAt: $occurredAt,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $summary
     */
    public static function progressed(
        string $runId,
        string $jobName,
        string $jobClass,
        string $progressKey,
        array $context,
        array $summary,
        int $durationMs,
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        return new self(
            idempotencyKey: self::key($runId, JobLifecycleStatus::PROGRESSED, $progressKey),
            runId: $runId,
            jobName: $jobName,
            jobClass: $jobClass,
            status: JobLifecycleStatus::PROGRESSED,
            context: [...$context, 'progress_key' => $progressKey],
            summary: $summary,
            durationMs: $durationMs,
            occurredAt: $occurredAt,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function failed(
        string $runId,
        string $jobName,
        string $jobClass,
        array $context,
        string $errorClass,
        string $errorMessage,
        int $durationMs,
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        return new self(
            idempotencyKey: self::key($runId, JobLifecycleStatus::FAILED),
            runId: $runId,
            jobName: $jobName,
            jobClass: $jobClass,
            status: JobLifecycleStatus::FAILED,
            context: $context,
            errorClass: $errorClass,
            errorMessage: $errorMessage,
            durationMs: $durationMs,
            occurredAt: $occurredAt,
        );
    }

    private static function key(string $runId, JobLifecycleStatus $status, ?string $suffix = null): string
    {
        $material = $runId . '|' . $status->value;

        if ($suffix !== null) {
            $material .= '|' . $suffix;
        }

        return hash('sha256', $material);
    }
}
