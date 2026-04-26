<?php

declare(strict_types=1);

namespace Nexus\Search\Domain;

/**
 * Aggregate root for a set of scholarly works retrieved by a search.
 * addWork() merges instead of duplicating when isSameWorkAs() returns true.
 */
final class CorpusSlice
{
    /** @var array<string, ScholarlyWork> keyed by primary WorkId string */
    private array $works = [];

    private function __construct(public readonly CorpusSliceId $id) {}

    public static function empty(): self
    {
        return new self(CorpusSliceId::generate());
    }

    public static function fromWorks(ScholarlyWork ...$works): self
    {
        $slice = self::empty();

        foreach ($works as $work) {
            $slice->addWork($work);
        }

        return $slice;
    }

    /**
     * @internal For test fixtures only — bypasses addWork() deduplication.
     * Each work is keyed by spl_object_id to guarantee uniqueness.
     */
    public static function fromWorksUnsafe(ScholarlyWork ...$works): self
    {
        $instance = new self(CorpusSliceId::generate());

        foreach ($works as $work) {
            $instance->works[(string) spl_object_id($work)] = $work;
        }

        return $instance;
    }

    /**
     * Add a work, merging into an existing one if isSameWorkAs() returns true.
     * Falls back to spl_object_hash when the work has no primary ID.
     */
    private function addWork(ScholarlyWork $work): void
    {
        $key = $work->primaryId()?->toString() ?? spl_object_hash($work);

        foreach ($this->works as $existingKey => $existing) {
            if ($existing->isSameWorkAs($work)) {
                $this->works[$existingKey] = $existing->mergeWith($work);

                return;
            }
        }

        $this->works[$key] = $work;
    }

    public function withWork(ScholarlyWork $work): self
    {
        $new = new self(CorpusSliceId::generate());
        $new->works = $this->works;
        $new->addWork($work);
        
        return $new;
    }

    public function contains(ScholarlyWork $work): bool
    {
        foreach ($this->works as $existing) {
            if ($existing->isSameWorkAs($work)) {
                return true;
            }
        }

        return false;
    }

    public function findById(\Nexus\Shared\ValueObject\WorkId $id): ?ScholarlyWork
    {
        $target = $id->toString();

        if (isset($this->works[$target])) {
            return $this->works[$target];
        }

        foreach ($this->works as $work) {
            foreach ($work->ids()->all() as $workId) {
                if ($workId->equals($id)) {
                    return $work;
                }
            }
        }

        return null;
    }

    public function findByTitle(string $normalizedTitle): ?ScholarlyWork
    {
        foreach ($this->works as $work) {
            if (mb_strtolower(trim($work->title())) === mb_strtolower(trim($normalizedTitle))) {
                return $work;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->works);
    }

    /** @return ScholarlyWork[] */
    public function all(): array
    {
        return array_values($this->works);
    }

    public function isEmpty(): bool
    {
        return $this->works === [];
    }

    /**
     * Merge another slice into a new slice.
     * Works already contained are merged; new works are appended.
     */
    public function merge(CorpusSlice $other): self
    {
        $new = new self(CorpusSliceId::generate());
        $new->works = $this->works;

        foreach ($other->works as $work) {
            $new->addWork($work);
        }

        return $new;
    }

    public function filter(callable $predicate): self
    {
        $new = new self(CorpusSliceId::generate());

        foreach ($this->works as $key => $work) {
            if ($predicate($work)) {
                $new->works[$key] = $work;
            }
        }

        return $new;
    }

    public function sortByYear(bool $descending = true): self
    {
        $sorted = $this->works;
        usort($sorted, function (ScholarlyWork $a, ScholarlyWork $b) use ($descending): int {
            $aYear = $a->year() ?? 0;
            $bYear = $b->year() ?? 0;

            return $descending ? $bYear <=> $aYear : $aYear <=> $bYear;
        });

        $new = new self(CorpusSliceId::generate());
        foreach ($sorted as $work) {
            $key = $work->primaryId()?->toString() ?? spl_object_hash($work);
            $new->works[$key] = $work;
        }

        return $new;
    }

    public function sortByCitedByCount(bool $descending = true): self
    {
        $sorted = $this->works;
        usort($sorted, function (ScholarlyWork $a, ScholarlyWork $b) use ($descending): int {
            $aCount = $a->citedByCount() ?? 0;
            $bCount = $b->citedByCount() ?? 0;

            return $descending ? $bCount <=> $aCount : $aCount <=> $bCount;
        });

        $new = new self(CorpusSliceId::generate());
        foreach ($sorted as $work) {
            $key = $work->primaryId()?->toString() ?? spl_object_hash($work);
            $new->works[$key] = $work;
        }

        return $new;
    }

    /** Returns works in $this not contained in $other. */
    public function subtract(CorpusSlice $other): self
    {
        return $this->filter(fn (ScholarlyWork $w) => ! $other->contains($w));
    }

    public function retracted(): self
    {
        return $this->filter(fn (ScholarlyWork $w) => $w->isRetracted());
    }

    public function withoutRetracted(): self
    {
        return $this->filter(fn (ScholarlyWork $w) => ! $w->isRetracted());
    }
}
