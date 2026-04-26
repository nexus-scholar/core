<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

/**
 * A lightweight dot-notation accessor for raw provider response arrays.
 * Eliminates null-check boilerplate in adapter normalization methods.
 */
final class FieldExtractor
{
    public function __construct(private readonly array $data) {}

    /**
     * Access a nested value using dot-notation (e.g. "bibjson.author.name").
     * Supports numeric indices for sequential arrays.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $parts   = explode('.', $path);
        $current = $this->data;

        foreach ($parts as $part) {
            if ($current === null || ! is_array($current)) {
                return $default;
            }

            if (is_numeric($part)) {
                $current = $current[(int) $part] ?? $default;
            } else {
                $current = $current[$part] ?? $default;
            }
        }

        return $current ?? $default;
    }

    public function getString(string $path, string $default = ''): string
    {
        $value = $this->get($path, $default);

        return $value !== null ? trim((string) $value) : $default;
    }

    public function getInt(string $path, ?int $default = null): ?int
    {
        $value = $this->get($path);

        if ($value === null) {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    public function getList(string $path, array $default = []): array
    {
        $value = $this->get($path, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * Return the first non-null value from multiple paths.
     */
    public function getFirst(string ...$paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->get($path);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
