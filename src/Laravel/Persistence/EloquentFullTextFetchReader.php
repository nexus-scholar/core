<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Nexus\Dissemination\Domain\FullTextFetchRecord;
use Nexus\Dissemination\Domain\FullTextStatus;
use Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort;
use Nexus\Laravel\Model\PdfFetchModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final readonly class EloquentFullTextFetchReader implements FullTextFetchReaderPort
{
    public function __construct(private ProjectCorpusWorksPort $corpusWorks) {}

    public function forWork(string|WorkId $workId, int $limit = 25): array
    {
        $internalWorkId = $this->internalWorkIdFor($workId);

        if ($internalWorkId === null) {
            return [];
        }

        return PdfFetchModel::query()
            ->where('work_id', $internalWorkId)
            ->orderByDesc('attempted_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (PdfFetchModel $model): FullTextFetchRecord => $this->recordFromModel($model))
            ->all();
    }

    public function forProject(string $projectId, int $limit = 100): array
    {
        $workIds = $this->corpusWorks->workIds($projectId);

        if ($workIds === []) {
            return [];
        }

        return PdfFetchModel::query()
            ->whereIn('work_id', $workIds)
            ->orderByDesc('attempted_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (PdfFetchModel $model): FullTextFetchRecord => $this->recordFromModel($model))
            ->all();
    }

    private function internalWorkIdFor(string|WorkId $workId): ?string
    {
        if (is_string($workId)) {
            if (! str_contains($workId, ':')) {
                return $workId;
            }

            try {
                $workId = WorkId::fromString($workId);
            } catch (\InvalidArgumentException) {
                return null;
            }
        }

        if ($workId->namespace === WorkIdNamespace::INTERNAL) {
            return $workId->value;
        }

        return WorkExternalIdModel::query()
            ->where('namespace', $workId->namespace->value)
            ->where('value', $workId->value)
            ->value('work_id');
    }

    private function recordFromModel(PdfFetchModel $model): FullTextFetchRecord
    {
        return new FullTextFetchRecord(
            id: (string) $model->id,
            workId: (string) $model->work_id,
            sourceAlias: (string) $model->source_alias,
            sourceUrl: $model->source_url === null ? null : (string) $model->source_url,
            status: FullTextStatus::from((string) $model->status),
            httpStatus: $model->http_status === null ? null : (int) $model->http_status,
            filePath: $model->file_path === null ? null : (string) $model->file_path,
            durationMs: $model->duration_ms === null ? null : (int) $model->duration_ms,
            errorMessage: $model->error_message === null ? null : (string) $model->error_message,
            attemptedAt: $this->dateTime($model->attempted_at),
            metadata: is_array($model->metadata) ? $model->metadata : [],
            createdAt: $this->nullableDateTime($model->created_at),
            updatedAt: $this->nullableDateTime($model->updated_at),
        );
    }

    private function nullableDateTime(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateTime($value);
    }

    private function dateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
