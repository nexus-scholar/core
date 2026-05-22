<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Port;

use Nexus\Screening\Domain\ScreeningWork;

interface ScreeningWorkSourcePort
{
    /**
     * @param  list<string>  $workIds
     * @param  list<string>  $queryIds
     * @return list<ScreeningWork>
     */
    public function forProject(string $projectId, ?int $limit = null, array $workIds = [], array $queryIds = []): array;
}
