<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain\Port;

use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Search\Domain\ScholarlyWork;

interface SnowballingProviderPort
{
    public function alias(): string;

    public function supports(ScholarlyWork $seed, SnowballDirection $direction): bool;

    /**
     * @return list<ScholarlyWork>
     */
    public function fetchCitingWorks(ScholarlyWork $seed, int $limit): array;

    /**
     * @return list<ScholarlyWork>
     */
    public function fetchReferencedWorks(ScholarlyWork $seed, int $limit): array;
}
