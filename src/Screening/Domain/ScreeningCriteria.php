<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

final readonly class ScreeningCriteria
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    private function __construct(private array $criteria) {}

    /**
     * @param  array<string, mixed>  $criteria
     */
    public static function fromArray(array $criteria): self
    {
        return new self(self::normalize($criteria));
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->criteria, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->criteria;
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }

        return $value;
    }
}
