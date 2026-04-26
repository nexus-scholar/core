<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

/**
 * Immutable value object representing a publication venue
 * (journal, conference, repository, book series, etc.).
 */
final class Venue
{
    public function __construct(
        public readonly string  $name,
        public readonly ?string $issn      = null,
        public readonly ?string $type      = null,
        // 'journal' | 'conference' | 'repository' | 'book' | null
        public readonly ?string $publisher = null,
    ) {}

    public function isJournal(): bool
    {
        return $this->type === 'journal';
    }

    public function isConference(): bool
    {
        return $this->type === 'conference';
    }
}
