<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

use DateTimeImmutable;

final readonly class ProjectLockState
{
    public function __construct(
        public string $projectId,
        public bool $isLocked,
        public string $status,
        public ?DateTimeImmutable $lockedAt = null,
        public ?string $lockedBy = null,
        public ?string $lockReason = null,
        public ?DateTimeImmutable $unlockedAt = null,
        public ?string $unlockedBy = null,
        public ?string $unlockReason = null,
    ) {}
}
