<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use DateTimeImmutable;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Dissemination\Domain\FullTextStatus;
use Nexus\Laravel\Model\PdfFetchModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Illuminate\Support\Str;

final class EloquentPdfFetchRepository implements PdfFetchRepositoryPort
{
    public function save(WorkId $workId, string $sourceUrl, FullTextResult $result, int $durationMs): void
    {
        $internalWorkId = $this->resolveInternalWorkId($workId);

        PdfFetchModel::create([
            'id'            => (string) Str::uuid(),
            'work_id'       => $internalWorkId,
            'source_alias'  => $result->sourceAlias,
            'source_url'    => $sourceUrl,
            'status'        => $result->status->value,
            'http_status'   => $result->httpStatus,
            'file_path'     => $result->filePath,
            'duration_ms'   => $durationMs,
            'error_message' => $result->errorMessage,
            'attempted_at'  => now(),
        ]);
    }

    public function findSuccessfulPath(WorkId $workId): ?string
    {
        $internalWorkId = $this->internalWorkIdFor($workId);

        if ($internalWorkId === null) {
            return null;
        }

        return PdfFetchModel::where('work_id', $internalWorkId)
            ->where('status', FullTextStatus::SUCCESS->value)
            ->whereNotNull('file_path')
            ->orderByDesc('attempted_at')
            ->value('file_path');
    }

    public function hasRecentFailure(WorkId $workId, string $sourceUrl, DateTimeImmutable $since): bool
    {
        $internalWorkId = $this->internalWorkIdFor($workId);

        if ($internalWorkId === null) {
            return false;
        }

        return PdfFetchModel::query()
            ->where('work_id', $internalWorkId)
            ->where('source_url', $sourceUrl)
            ->where('status', FullTextStatus::FAILURE->value)
            ->where('attempted_at', '>=', $since->format('Y-m-d H:i:s'))
            ->exists();
    }

    private function resolveInternalWorkId(WorkId $workId): string
    {
        $internalWorkId = $this->internalWorkIdFor($workId);

        if ($internalWorkId === null) {
            throw new \InvalidArgumentException("Cannot save PDF fetch for unpersisted work {$workId->toString()}.");
        }

        return $internalWorkId;
    }

    private function internalWorkIdFor(WorkId $workId): ?string
    {
        if ($workId->namespace === WorkIdNamespace::INTERNAL) {
            return $workId->value;
        }

        return WorkExternalIdModel::query()
            ->where('namespace', $workId->namespace->value)
            ->where('value', $workId->value)
            ->value('work_id');
    }
}
