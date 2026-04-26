<?php

declare(strict_types=1);

namespace Nexus\Search\Domain;

use Nexus\Search\Domain\Exception\InvalidYearRange;

final class YearRange
{
    private static function currentYear(): int
    {
        return (int) date('Y');
    }

    public function __construct(
        public readonly ?int $from = null,
        public readonly ?int $to   = null,
    ) {
        $maxYear = self::currentYear() + 5;

        if ($this->from !== null && ($this->from < 1000 || $this->from > $maxYear)) {
            throw new InvalidYearRange("Year {$this->from} is out of valid range [1000, {$maxYear}].");
        }

        if ($this->to !== null && ($this->to < 1000 || $this->to > $maxYear)) {
            throw new InvalidYearRange("Year {$this->to} is out of valid range [1000, {$maxYear}].");
        }

        if ($this->from !== null && $this->to !== null && $this->from > $this->to) {
            throw new InvalidYearRange(
                "Year range is inverted: from={$this->from} > to={$this->to}."
            );
        }
    }

    public static function since(int $year): self
    {
        return new self(from: $year);
    }

    public static function until(int $year): self
    {
        return new self(to: $year);
    }

    public static function between(int $from, int $to): self
    {
        return new self(from: $from, to: $to);
    }

    public static function unbounded(): self
    {
        return new self();
    }

    public function contains(int $year): bool
    {
        if ($this->from !== null && $year < $this->from) {
            return false;
        }

        if ($this->to !== null && $year > $this->to) {
            return false;
        }

        return true;
    }

    public function isUnbounded(): bool
    {
        return $this->from === null && $this->to === null;
    }

    public function overlaps(YearRange $other): bool
    {
        $aFrom = $this->from ?? PHP_INT_MIN;
        $aTo   = $this->to   ?? PHP_INT_MAX;
        $bFrom = $other->from ?? PHP_INT_MIN;
        $bTo   = $other->to   ?? PHP_INT_MAX;

        return $aFrom <= $bTo && $bFrom <= $aTo;
    }
}
