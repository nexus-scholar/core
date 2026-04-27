<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain;

use Nexus\Shared\ValueObject\WorkId;

final class CitationLink
{
    public function __construct(
        public readonly WorkId $citing,
        public readonly WorkId $cited,
        public readonly float  $weight = 1.0,
    ) {
    }

    public function involves(WorkId $id): bool
    {
        return $this->citing->equals($id) || $this->cited->equals($id);
    }

    public function equals(CitationLink $other): bool
    {
        return $this->citing->equals($other->citing) && $this->cited->equals($other->cited);
    }

    public function reversed(): self
    {
        return new self($this->cited, $this->citing, $this->weight);
    }
}
