<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Support\Facades\DB;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;

final class EloquentExportHistoryRecorder implements ExportHistoryPort
{
    public function record(ExportHistoryRecord $record): void
    {
        $createdAt = $record->createdAt ?? new \DateTimeImmutable();

        DB::table('export_histories')->insert([
            'id' => $record->id,
            'type' => $record->type->value,
            'format' => $record->format,
            'filename' => $record->filename,
            'path' => $record->path,
            'mime_type' => $record->mimeType,
            'size_bytes' => $record->sizeBytes,
            'project_id' => $record->projectId,
            'corpus_slice_id' => $record->corpusSliceId,
            'citation_graph_id' => $record->citationGraphId,
            'requested_by' => $record->requestedBy,
            'metadata' => $record->metadata === [] ? null : json_encode($record->metadata, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
