<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

/**
 * Immutable collection of WorkIds for a single scholarly work.
 * A work may have multiple IDs (DOI + OpenAlex + arXiv).
 * Primary ID is returned according to a fixed precedence order.
 */
final class WorkIdSet
{
    /** @var WorkId[] */
    private array $ids;

    /** Precedence order for primary() — index = priority (lower = higher priority) */
    private const PRIMARY_ORDER = [
        WorkIdNamespace::DOI,
        WorkIdNamespace::OPENALEX,
        WorkIdNamespace::S2,
        WorkIdNamespace::ARXIV,
        WorkIdNamespace::PUBMED,
        WorkIdNamespace::IEEE,
        WorkIdNamespace::DOAJ,
    ];

    public function __construct(WorkId ...$ids)
    {
        $this->ids = array_values($ids);
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @param WorkId[] $ids */
    public static function fromArray(array $ids): self
    {
        return new self(...$ids);
    }

    /** Returns a new instance with the given id appended. */
    public function add(WorkId $id): self
    {
        $new = clone $this;
        $new->ids = [...$this->ids, $id];

        return $new;
    }

    public function findByNamespace(WorkIdNamespace $ns): ?WorkId
    {
        foreach ($this->ids as $id) {
            if ($id->namespace === $ns) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Return the most authoritative ID based on the fixed precedence order.
     * Returns null if the set is empty.
     */
    public function primary(): ?WorkId
    {
        foreach (self::PRIMARY_ORDER as $ns) {
            $found = $this->findByNamespace($ns);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Returns true if any ID in $this matches any ID in $other
     * (same namespace + same normalized value).
     */
    public function hasOverlapWith(WorkIdSet $other): bool
    {
        foreach ($this->ids as $a) {
            foreach ($other->ids as $b) {
                if ($a->equals($b)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /** @return WorkId[] */
    public function all(): array
    {
        return $this->ids;
    }

    /** Returns a new instance combining both sets (duplicates are allowed). */
    public function merge(WorkIdSet $other): self
    {
        return new self(...$this->ids, ...$other->ids);
    }

    /** "doi:10.x|arxiv:2301.x" */
    public function toString(): string
    {
        return implode('|', array_map(fn (WorkId $id) => $id->toString(), $this->ids));
    }
}
