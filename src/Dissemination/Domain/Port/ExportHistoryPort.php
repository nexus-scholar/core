<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\Dissemination\Domain\ExportHistoryRecord;

interface ExportHistoryPort
{
    public function record(ExportHistoryRecord $record): void;
}
