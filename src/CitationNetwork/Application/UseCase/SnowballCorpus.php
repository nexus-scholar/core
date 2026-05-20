<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use InvalidArgumentException;
use Nexus\Search\Domain\CorpusSlice;

final class SnowballCorpus
{
    /** @var list<string> */
    public readonly array $providerAliases;

    public function __construct(
        public readonly string $projectId,
        public readonly CorpusSlice $seedCorpus,
        public readonly ?CorpusSlice $knownCorpus = null,
        public readonly bool $forward = true,
        public readonly bool $backward = true,
        public readonly int $depth = 1,
        public readonly int $maxCitations = 100,
        public readonly int $maxReferences = 50,
        array $providerAliases = [],
    ) {
        if (trim($projectId) === '') {
            throw new InvalidArgumentException('Snowball project id must not be empty.');
        }

        if (! $forward && ! $backward) {
            throw new InvalidArgumentException('Snowballing requires at least one direction.');
        }

        if ($depth < 1) {
            throw new InvalidArgumentException('Snowball depth must be at least 1.');
        }

        if ($maxCitations < 1) {
            throw new InvalidArgumentException('Snowball max citations must be at least 1.');
        }

        if ($maxReferences < 1) {
            throw new InvalidArgumentException('Snowball max references must be at least 1.');
        }

        $this->providerAliases = array_values(array_unique(array_map(
            static fn (string $alias): string => strtolower(trim($alias)),
            array_filter($providerAliases, static fn (mixed $alias): bool => is_string($alias) && trim($alias) !== ''),
        )));
    }

    public function initialKnownCorpus(): CorpusSlice
    {
        return $this->knownCorpus ?? $this->seedCorpus;
    }
}
