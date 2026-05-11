<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

final class FullTextSourceCollection
{
    /** @var FullTextSourcePort[] */
    private array $sources;

    public function __construct(FullTextSourcePort ...$sources)
    {
        $this->sources = $sources;
    }

    /** @return FullTextSourcePort[] */
    public function all(): array
    {
        return $this->sources;
    }
}
