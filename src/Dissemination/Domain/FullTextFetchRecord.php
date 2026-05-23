<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

use DateTimeImmutable;

final readonly class FullTextFetchRecord
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $workId,
        public string $sourceAlias,
        public ?string $sourceUrl,
        public FullTextStatus $status,
        public ?int $httpStatus,
        public ?string $filePath,
        public ?int $durationMs,
        public ?string $errorMessage,
        public DateTimeImmutable $attemptedAt,
        public array $metadata = [],
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}
}
