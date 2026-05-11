<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

final class SerializerCollection
{
    /** @var BibliographySerializerPort[] */
    private array $serializers;

    public function __construct(BibliographySerializerPort ...$serializers)
    {
        $this->serializers = $serializers;
    }

    /** @return BibliographySerializerPort[] */
    public function all(): array
    {
        return $this->serializers;
    }
}
