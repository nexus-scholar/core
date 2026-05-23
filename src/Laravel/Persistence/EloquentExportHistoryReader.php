<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\ExportType;
use Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort;

final class EloquentExportHistoryReader implements ExportHistoryReaderPort
{
    public function find(string $id): ?ExportHistoryRecord
    {
        $row = DB::table('export_histories')->where('id', $id)->first();

        return $row === null ? null : $this->recordFromRow($row);
    }

    public function latest(?string $projectId = null, ?string $type = null, int $limit = 25): array
    {
        $query = DB::table('export_histories')
            ->when($projectId !== null, fn (Builder $query) => $query->where('project_id', $projectId))
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit));

        return $query
            ->get()
            ->map(fn (object $row): ExportHistoryRecord => $this->recordFromRow($row))
            ->all();
    }

    private function recordFromRow(object $row): ExportHistoryRecord
    {
        return new ExportHistoryRecord(
            id: (string) $row->id,
            type: ExportType::from((string) $row->type),
            format: (string) $row->format,
            filename: (string) $row->filename,
            path: (string) $row->path,
            mimeType: (string) $row->mime_type,
            sizeBytes: (int) $row->size_bytes,
            projectId: $row->project_id === null ? null : (string) $row->project_id,
            corpusSliceId: $row->corpus_slice_id === null ? null : (string) $row->corpus_slice_id,
            citationGraphId: $row->citation_graph_id === null ? null : (string) $row->citation_graph_id,
            requestedBy: $row->requested_by === null ? null : (string) $row->requested_by,
            metadata: $this->arrayValue($row->metadata ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function dateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
