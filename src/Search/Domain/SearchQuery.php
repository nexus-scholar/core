<?php

declare(strict_types=1);

namespace Nexus\Search\Domain;

use Nexus\Shared\ValueObject\LanguageCode;

/**
 * An immutable search query. Generates its own crypto-random ID.
 * The cacheKey() method is the ONLY authoritative source of cache key computation.
 */
final class SearchQuery
{
    public readonly string $id;

    public function __construct(
        public readonly SearchTerm    $term,
        public readonly string        $projectId      = 'default-project',
        public readonly ?YearRange    $yearRange      = null,
        public readonly ?LanguageCode $language       = null,
        public readonly int           $maxResults     = 100,
        public readonly int           $offset         = 0,
        public readonly bool          $includeRawData = false,
        ?string $id                                   = null,
    ) {
        // NEVER use uniqid() — see known bug #13
        $this->id = $id ?? ('Q' . bin2hex(random_bytes(5)));
    }

    /**
     * Authoritative cache key covering all query dimensions.
     * The agent MUST NOT compute a cache key outside this method.
     *
     * @param string[] $sortedProviderAliases
     */
    public function cacheKey(array $sortedProviderAliases = []): string
    {
        sort($sortedProviderAliases);

        $parts = [
            $this->term->value,
            (string) ($this->yearRange?->from ?? ''),
            (string) ($this->yearRange?->to ?? ''),
            $this->language?->value ?? '',
            (string) $this->maxResults,
            (string) $this->offset,
            implode(',', $sortedProviderAliases),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public function withOffset(int $offset): self
    {
        return new self(
            term:           $this->term,
            projectId:      $this->projectId,
            yearRange:      $this->yearRange,
            language:       $this->language,
            maxResults:     $this->maxResults,
            offset:         $offset,
            includeRawData: $this->includeRawData,
            id:             $this->id,
        );
    }

    public function withMaxResults(int $max): self
    {
        return new self(
            term:           $this->term,
            projectId:      $this->projectId,
            yearRange:      $this->yearRange,
            language:       $this->language,
            maxResults:     $max,
            offset:         $this->offset,
            includeRawData: $this->includeRawData,
            id:             $this->id,
        );
    }

    public function nextPage(): self
    {
        return $this->withOffset($this->offset + $this->maxResults);
    }

    public function isFirstPage(): bool
    {
        return $this->offset === 0;
    }
}
