<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

final class UnlockCorpus
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $projectId,
        public readonly ?string $actorId = null,
        public readonly ?string $reason = null,
        public readonly array $metadata = [],
    ) {}
}
