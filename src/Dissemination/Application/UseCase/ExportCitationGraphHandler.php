<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Dissemination\Application\Support\ValidatesExportFilename;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\ExportType;
use Nexus\Dissemination\Domain\Port\CitationGraphSerializerCollection;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use RuntimeException;

final readonly class ExportCitationGraphHandler
{
    use ValidatesExportFilename;

    public function __construct(
        private CitationGraphSerializerCollection $serializers,
        private FileStoragePort                   $storage,
        private ?ExportHistoryPort                $history = null,
    ) {}

    public function handle(ExportCitationGraph $command): string
    {
        $this->assertFilenameMatchesExtension(
            $command->filename,
            $command->format->extension(),
            $command->format->value,
        );

        foreach ($this->serializers->all() as $serializer) {
            if ($serializer->supports($command->format)) {
                $content = $serializer->serialize($command->graph, $command->format);
                $path = $this->storage->store($command->filename, $content);

                $this->history?->record(ExportHistoryRecord::create(
                    type: ExportType::NETWORK,
                    format: $command->format->value,
                    filename: $command->filename,
                    path: $path,
                    mimeType: $command->format->mimeType(),
                    sizeBytes: strlen($content),
                    projectId: $command->graph->projectId,
                    citationGraphId: $command->graph->id->value,
                    requestedBy: $command->requestedBy,
                    metadata: $command->metadata,
                ));

                return $path;
            }
        }

        throw new RuntimeException("No citation graph serializer found for format: {$command->format->value}");
    }
}
