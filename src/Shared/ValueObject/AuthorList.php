<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

/**
 * Immutable ordered collection of Author value objects.
 */
final class AuthorList
{
    /** @var Author[] */
    private array $authors;

    public function __construct(Author ...$authors)
    {
        $this->authors = array_values($authors);
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @param Author[] $authors */
    public static function fromArray(array $authors): self
    {
        return new self(...$authors);
    }

    public function first(): ?Author
    {
        return $this->authors[0] ?? null;
    }

    public function last(): ?Author
    {
        return $this->authors === [] ? null : $this->authors[count($this->authors) - 1];
    }

    public function count(): int
    {
        return count($this->authors);
    }

    /** @return Author[] */
    public function all(): array
    {
        return $this->authors;
    }

    public function get(int $position): ?Author
    {
        return $this->authors[$position] ?? null;
    }

    /**
     * Returns a new AuthorList containing only authors that appear in both lists
     * (matched by ORCID or normalized name).
     */
    public function intersect(AuthorList $other): self
    {
        $matched = [];

        foreach ($this->authors as $mine) {
            foreach ($other->authors as $theirs) {
                if ($mine->isSamePerson($theirs)) {
                    $matched[] = $mine;
                    break;
                }
            }
        }

        return new self(...$matched);
    }

    public function isEmpty(): bool
    {
        return $this->authors === [];
    }
}
