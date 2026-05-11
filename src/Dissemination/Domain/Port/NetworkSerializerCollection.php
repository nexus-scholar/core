<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

final class NetworkSerializerCollection
{
    /** @var NetworkSerializerPort[] */
    private array $serializers;

    public function __construct(NetworkSerializerPort ...$serializers)
    {
        $this->serializers = $serializers;
    }

    /** @return NetworkSerializerPort[] */
    public function all(): array
    {
        return $this->serializers;
    }
}
