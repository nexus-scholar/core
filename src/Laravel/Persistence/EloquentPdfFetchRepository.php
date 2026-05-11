<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Dissemination\Domain\FullTextStatus;
use Nexus\Laravel\Model\PdfFetchModel;
use Nexus\Shared\ValueObject\WorkId;
use Illuminate\Support\Str;

final class EloquentPdfFetchRepository implements PdfFetchRepositoryPort
{
    public function save(WorkId $workId, string $sourceUrl, FullTextResult $result, int $durationMs): void
    {
        PdfFetchModel::create([
            'id'            => (string) Str::uuid(),
            'work_id'       => $workId->toString(),
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
        return PdfFetchModel::where('work_id', $workId->toString())
            ->where('status', FullTextStatus::SUCCESS->value)
            ->whereNotNull('file_path')
            ->orderByDesc('attempted_at')
            ->value('file_path');
    }
}
