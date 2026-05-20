<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Plan;

use Nexus\Search\Application\UseCase\SearchAcrossProviders;

final readonly class SearchPlanItem
{
    /**
     * @param list<string> $providerAliases
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $query,
        public string $projectId,
        public int $maxResults = 50,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public array $providerAliases = [],
        public bool $includeRawData = false,
        public array $metadata = [],
        public ?string $priority = null,
        public ?string $includeTitleAbstract = null,
        public ?string $excludeTitleAbstract = null,
        public int $sourceIndex = 0,
    ) {
        if (trim($this->id) === '') {
            throw SearchPlanException::invalid('Search plan item id must not be empty.');
        }

        if (trim($this->query) === '') {
            throw SearchPlanException::invalid("Search plan item {$this->id} query must not be empty.");
        }

        if ($this->maxResults < 1) {
            throw SearchPlanException::invalid("Search plan item {$this->id} max results must be at least 1.");
        }
    }

    /**
     * @param list<string> $providerAliases
     */
    public function withOverrides(
        ?string $projectId = null,
        ?int $maxResults = null,
        array $providerAliases = [],
    ): self {
        return new self(
            id: $this->id,
            label: $this->label,
            query: $this->query,
            projectId: $projectId ?? $this->projectId,
            maxResults: $maxResults ?? $this->maxResults,
            yearFrom: $this->yearFrom,
            yearTo: $this->yearTo,
            providerAliases: $providerAliases === [] ? $this->providerAliases : $providerAliases,
            includeRawData: $this->includeRawData,
            metadata: $this->metadata,
            priority: $this->priority,
            includeTitleAbstract: $this->includeTitleAbstract,
            excludeTitleAbstract: $this->excludeTitleAbstract,
            sourceIndex: $this->sourceIndex,
        );
    }

    public function toSearchCommand(): SearchAcrossProviders
    {
        return new SearchAcrossProviders(
            query: $this->query,
            projectId: $this->projectId,
            maxResults: $this->maxResults,
            yearFrom: $this->yearFrom,
            yearTo: $this->yearTo,
            providerAliases: $this->providerAliases,
            includeRawData: $this->includeRawData,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function metadataForRun(): array
    {
        $metadata = $this->metadata;
        $metadata['query_id'] = $this->id;
        $metadata['label'] = $this->label;
        $metadata['query'] = $this->query;
        $metadata['limit'] = $this->maxResults;

        if ($this->yearFrom !== null) {
            $metadata['year_from'] = $this->yearFrom;
        }

        if ($this->yearTo !== null) {
            $metadata['year_to'] = $this->yearTo;
        }

        if ($this->providerAliases !== []) {
            $metadata['providers'] = $this->providerAliases;
        }

        if ($this->includeRawData) {
            $metadata['include_raw_data'] = true;
        }

        if ($this->includeTitleAbstract !== null) {
            $metadata['include_title_abstract'] = $this->includeTitleAbstract;
        }

        if ($this->excludeTitleAbstract !== null) {
            $metadata['exclude_title_abstract'] = $this->excludeTitleAbstract;
        }

        return $metadata;
    }
}
