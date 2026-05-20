<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Plan;

final readonly class SearchPlanRunOptions
{
    /**
     * @param list<string> $onlyIds
     * @param list<string> $providerAliases
     */
    public function __construct(
        public array $onlyIds = [],
        public ?string $priority = null,
        public ?string $projectId = null,
        public ?int $maxResults = null,
        public array $providerAliases = [],
        public bool $continueOnFailure = true,
    ) {
        if ($this->maxResults !== null && $this->maxResults < 1) {
            throw SearchPlanException::invalid('Search plan max results override must be at least 1.');
        }
    }
}
