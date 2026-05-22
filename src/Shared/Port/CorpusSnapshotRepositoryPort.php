<?php

declare(strict_types=1);

namespace Nexus\Shared\Port;

use DateTimeImmutable;
use Nexus\Shared\ValueObject\CorpusSnapshot;

interface CorpusSnapshotRepositoryPort
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createForLockedProject(
        string $projectId,
        DateTimeImmutable $lockedAt,
        ?string $actorId = null,
        ?string $reason = null,
        array $metadata = [],
    ): CorpusSnapshot;

    public function latestForProject(string $projectId): ?CorpusSnapshot;
}
