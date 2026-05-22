<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

final readonly class ScreeningWork
{
    /**
     * @param  array<string, string>  $identifiers
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $abstract = null,
        public ?int $year = null,
        public ?string $venue = null,
        public ?string $sourceProvider = null,
        public array $identifiers = [],
        public array $metadata = [],
    ) {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('Screening work id must not be empty.');
        }

        if (trim($title) === '') {
            throw new \InvalidArgumentException('Screening work title must not be empty.');
        }
    }

    public function hasAbstract(): bool
    {
        return $this->abstract !== null && trim($this->abstract) !== '';
    }
}
