<?php

declare(strict_types=1);

namespace Nexus\Shared\Port;

interface ProjectWorkMembershipPort
{
    /**
     * @param  list<string>  $workIds  Internal scholarly_works IDs or WorkId strings such as doi:10.x.
     * @return list<string>
     */
    public function missingWorkIds(string $projectId, array $workIds): array;
}
