<?php

declare(strict_types=1);

namespace Nexus\Search\Domain;

use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdSet;

/**
 * An individual scholarly work retrieved from an academic provider.
 *
 * Private constructor — always create via ::reconstitute().
 * rawData is null by default — only populated when query->includeRawData === true.
 */
final class ScholarlyWork
{
    private function __construct(
        private WorkIdSet          $ids,
        private string             $title,
        private AuthorList         $authors,
        private ?int               $year,
        private ?Venue             $venue,
        private ?string            $abstract,
        private ?int               $citedByCount,
        private bool               $isRetracted,
        private string             $sourceProvider,
        private \DateTimeImmutable $retrievedAt,
        private ?array             $rawData,
    ) {}

    public static function reconstitute(
        WorkIdSet   $ids,
        string      $title,
        string      $sourceProvider,
        ?int        $year         = null,
        ?AuthorList $authors      = null,
        ?Venue      $venue        = null,
        ?string     $abstract     = null,
        ?int        $citedByCount = null,
        bool        $isRetracted  = false,
        ?array      $rawData      = null,
    ): self {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('ScholarlyWork title must not be empty.');
        }

        return new self(
            ids:            $ids,
            title:          $title,
            authors:        $authors ?? AuthorList::empty(),
            year:           $year,
            venue:          $venue,
            abstract:       $abstract,
            citedByCount:   $citedByCount,
            isRetracted:    $isRetracted,
            sourceProvider: $sourceProvider,
            retrievedAt:    new \DateTimeImmutable(),
            rawData:        $rawData,  // null unless explicitly passed
        );
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function ids(): WorkIdSet
    {
        return $this->ids;
    }

    public function primaryId(): ?WorkId
    {
        return $this->ids->primary();
    }

    public function title(): string
    {
        return $this->title;
    }

    public function authors(): AuthorList
    {
        return $this->authors;
    }

    public function year(): ?int
    {
        return $this->year;
    }

    public function venue(): ?Venue
    {
        return $this->venue;
    }

    public function abstract(): ?string
    {
        return $this->abstract;
    }

    public function citedByCount(): ?int
    {
        return $this->citedByCount;
    }

    public function isRetracted(): bool
    {
        return $this->isRetracted;
    }

    public function sourceProvider(): string
    {
        return $this->sourceProvider;
    }

    public function retrievedAt(): \DateTimeImmutable
    {
        return $this->retrievedAt;
    }

    public function rawData(): ?array
    {
        return $this->rawData;
    }

    // ── Domain behaviour ─────────────────────────────────────────────────────

    /**
     * Identity check — delegates entirely to WorkIdSet::hasOverlapWith().
     * Never compare by title here.
     */
    public function isSameWorkAs(ScholarlyWork $other): bool
    {
        return $this->ids->hasOverlapWith($other->ids);
    }

    /**
     * Returns a new ScholarlyWork combining both.
     * $this is the base — fields from $other are used only to fill nulls.
     * Existing fields on $this are NEVER overwritten.
     */
    public function mergeWith(ScholarlyWork $other): self
    {
        $merged = clone $this;
        $merged->ids      = $this->ids->merge($other->ids);
        $merged->authors  = $this->authors->isEmpty() ? $other->authors : $this->authors;
        $merged->year     = $this->year     ?? $other->year;
        $merged->venue    = $this->venue    ?? $other->venue;
        $merged->abstract = $this->abstract ?? $other->abstract;
        $merged->citedByCount = $this->citedByCount ?? $other->citedByCount;

        return $merged;
    }

    public function withRawData(array $raw): self
    {
        $clone = clone $this;
        $clone->rawData = $raw;

        return $clone;
    }

    public function withoutRawData(): self
    {
        $clone = clone $this;
        $clone->rawData = null;

        return $clone;
    }

    public function hasAbstract(): bool
    {
        return $this->abstract !== null && trim($this->abstract) !== '';
    }

    public function hasVenue(): bool
    {
        return $this->venue !== null;
    }

    public function isPreprint(): bool
    {
        return $this->sourceProvider === 'arxiv'
            || $this->venue?->type === 'repository';
    }

    /**
     * Completeness score 0–10 for representative election.
     * Fields scored: doi(2), abstract(2), venue(1), authors(1), year(1),
     *                citedByCount(1), hasOrcid(1), isNotRetracted(1)
     */
    public function completenessScore(): int
    {
        $score = 0;

        if ($this->ids->findByNamespace(\Nexus\Shared\ValueObject\WorkIdNamespace::DOI) !== null) {
            $score += 2;
        }

        if ($this->hasAbstract()) {
            $score += 2;
        }

        if ($this->hasVenue()) {
            $score += 1;
        }

        if (! $this->authors->isEmpty()) {
            $score += 1;
        }

        if ($this->year !== null) {
            $score += 1;
        }

        if ($this->citedByCount !== null) {
            $score += 1;
        }

        if (! $this->isRetracted) {
            $score += 1;
        }

        // bonus: any author has ORCID
        foreach ($this->authors->all() as $author) {
            if ($author->hasOrcid()) {
                $score += 1;
                break;
            }
        }

        return min($score, 10);
    }
}
