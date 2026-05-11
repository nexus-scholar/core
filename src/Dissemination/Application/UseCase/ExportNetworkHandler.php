<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\NetworkSerializerCollection;
use RuntimeException;

final readonly class ExportNetworkHandler
{
    public function __construct(
        private NetworkSerializerCollection $serializers,
        private FileStoragePort             $storage,
    ) {}

    public function handle(ExportNetwork $command): string
    {
        foreach ($this->serializers->all() as $serializer) {
            if ($serializer->supports($command->format)) {
                $content = $serializer->serialize($command->corpus);
                
                return $this->storage->store($command->filename, $content);
            }
        }

        throw new RuntimeException("No network serializer found for format: {$command->format->value}");
    }
}
