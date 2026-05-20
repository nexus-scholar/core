<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use DateTimeImmutable;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Shared\ValueObject\WorkId;

interface PdfFetchRepositoryPort
{
    public function save(WorkId $workId, string $sourceUrl, FullTextResult $result, int $durationMs): void;
    
    /**
     * Check if a successful fetch already exists for this work.
     */
    public function findSuccessfulPath(WorkId $workId): ?string;

    /**
     * Check if the same source URL recently failed for this work.
     */
    public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool;
}
