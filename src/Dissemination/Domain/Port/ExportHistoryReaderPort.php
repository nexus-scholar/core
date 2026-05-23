<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\Dissemination\Domain\ExportHistoryRecord;

interface ExportHistoryReaderPort
{
    public function find(string $id): ?ExportHistoryRecord;

    /**
     * @return list<ExportHistoryRecord>
     */
    public function latest(?string $projectId = null, ?string $type = null, int $limit = 25): array;
}
