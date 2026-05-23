<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\Dissemination\Domain\FullTextFetchRecord;
use Nexus\Shared\ValueObject\WorkId;

interface FullTextFetchReaderPort
{
    /**
     * @return list<FullTextFetchRecord>
     */
    public function forWork(string|WorkId $workId, int $limit = 25): array;

    /**
     * @return list<FullTextFetchRecord>
     */
    public function forProject(string $projectId, int $limit = 100): array;
}
