<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\Shared\Domain\ScholarlyWork;

interface FullTextSourcePort
{
    /**
     * Resolves the PDF URL for a given work.
     * Returns null if no PDF could be found.
     */
    public function resolve(ScholarlyWork $work): ?string;

    /**
     * Unique alias for the source (e.g., 'arxiv', 'openalex').
     */
    public function alias(): string;

    /**
     * Checks if this source can potentially resolve this work.
     */
    public function supports(ScholarlyWork $work): bool;
}
