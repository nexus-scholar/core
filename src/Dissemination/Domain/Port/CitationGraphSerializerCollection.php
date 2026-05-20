<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

final class CitationGraphSerializerCollection
{
    /** @var CitationGraphSerializerPort[] */
    private array $serializers;

    public function __construct(CitationGraphSerializerPort ...$serializers)
    {
        $this->serializers = $serializers;
    }

    /** @return CitationGraphSerializerPort[] */
    public function all(): array
    {
        return $this->serializers;
    }
}
