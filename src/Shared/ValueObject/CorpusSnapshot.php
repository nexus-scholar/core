<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

use DateTimeImmutable;

final readonly class CorpusSnapshot
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $projectId,
        public DateTimeImmutable $lockedAt,
        public int $workCount,
        public ?string $createdBy = null,
        public ?string $lockReason = null,
        public array $metadata = [],
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
