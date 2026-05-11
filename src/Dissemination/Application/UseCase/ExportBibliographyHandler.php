<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use RuntimeException;

final readonly class ExportBibliographyHandler
{
    public function __construct(
        private SerializerCollection $serializers,
        private FileStoragePort      $storage,
    ) {}

    public function handle(ExportBibliography $command): string
    {
        foreach ($this->serializers->all() as $serializer) {
            if ($serializer->supports($command->format)) {
                $content = $serializer->serialize($command->corpus);
                
                return $this->storage->store($command->filename, $content);
            }
        }

        throw new RuntimeException("No serializer found for format: {$command->format->value}");
    }
}
