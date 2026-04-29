<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Application;

final class LockCorpus
{
    public function __construct(
        public readonly string $projectId
    ) {}
}
