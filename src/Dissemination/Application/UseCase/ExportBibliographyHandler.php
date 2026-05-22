<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Dissemination\Application\Support\ValidatesExportFilename;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\ExportType;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use Nexus\Shared\Application\CorpusLockPolicy;
use RuntimeException;

final readonly class ExportBibliographyHandler
{
    use ValidatesExportFilename;

    public function __construct(
        private SerializerCollection $serializers,
        private FileStoragePort $storage,
        private ?ExportHistoryPort $history = null,
        private ?CorpusLockPolicy $lockPolicy = null,
    ) {}

    public function handle(ExportBibliography $command): string
    {
        $this->assertFilenameMatchesExtension(
            $command->filename,
            $command->format->extension(),
            $command->format->value,
        );

        foreach ($this->serializers->all() as $serializer) {
            if ($serializer->supports($command->format)) {
                $content = $serializer->serialize($command->corpus);
                $path = $this->storage->store($command->filename, $content);

                $metadata = $command->metadata;
                if ($this->lockPolicy !== null) {
                    $metadata = [
                        ...$metadata,
                        ...$this->lockPolicy->exportMetadata($command->projectId),
                    ];
                }

                $this->history?->record(ExportHistoryRecord::create(
                    type: ExportType::BIBLIOGRAPHY,
                    format: $command->format->value,
                    filename: $command->filename,
                    path: $path,
                    mimeType: $command->format->mimeType(),
                    sizeBytes: strlen($content),
                    projectId: $command->projectId,
                    corpusSliceId: $command->corpus->id->value,
                    requestedBy: $command->requestedBy,
                    metadata: $metadata,
                ));

                return $path;
            }
        }

        throw new RuntimeException("No serializer found for format: {$command->format->value}");
    }
}
