<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

final readonly class FullTextSourceConfig
{
    public function __construct(
        public string $alias,
        public string $baseUrl,
        public bool $enabled = true,
        public float $ratePerSecond = 1.0,
        public int $timeoutSeconds = 10,
        public int $maxRetries = 2,
        public ?string $email = null,
        public bool $preferPdf = true,
        public bool $preferXml = false,
    ) {
        if (trim($alias) === '') {
            throw new \InvalidArgumentException('Full-text source alias cannot be empty.');
        }

        if (trim($baseUrl) === '') {
            throw new \InvalidArgumentException("Full-text source {$alias} base URL cannot be empty.");
        }

        if ($ratePerSecond <= 0) {
            throw new \InvalidArgumentException("Full-text source {$alias} rate limit must be greater than zero.");
        }

        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException("Full-text source {$alias} timeout must be greater than zero.");
        }

        if ($maxRetries < 1) {
            throw new \InvalidArgumentException("Full-text source {$alias} max retries must be at least one.");
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(string $alias, string $baseUrl, array $values = [], array $defaults = []): self
    {
        return new self(
            alias: $alias,
            baseUrl: self::stringOrDefault($values['base_url'] ?? null, $baseUrl, "{$alias}.base_url"),
            enabled: self::boolOrDefault($values['enabled'] ?? null, (bool) ($defaults['enabled'] ?? true)),
            ratePerSecond: self::floatOrDefault(
                $values['rate_limit'] ?? $values['rate_per_second'] ?? null,
                (float) ($defaults['rate_limit'] ?? $defaults['rate_per_second'] ?? 1.0),
                "{$alias}.rate_limit",
            ),
            timeoutSeconds: self::intOrDefault(
                $values['timeout'] ?? $values['timeout_seconds'] ?? null,
                (int) ($defaults['timeout'] ?? $defaults['timeout_seconds'] ?? 10),
                "{$alias}.timeout",
            ),
            maxRetries: self::intOrDefault(
                $values['max_retries'] ?? $values['retries'] ?? null,
                (int) ($defaults['max_retries'] ?? $defaults['retries'] ?? 2),
                "{$alias}.max_retries",
            ),
            email: self::nullableString($values['email'] ?? $defaults['email'] ?? null),
            preferPdf: self::boolOrDefault($values['prefer_pdf'] ?? null, (bool) ($defaults['prefer_pdf'] ?? true)),
            preferXml: self::boolOrDefault($values['prefer_xml'] ?? null, (bool) ($defaults['prefer_xml'] ?? false)),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function stringOrDefault(mixed $value, string $default, string $key): string
    {
        if ($value === null) {
            return $default;
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new \InvalidArgumentException("Full-text source config {$key} cannot be empty.");
        }

        return $value;
    }

    private static function floatOrDefault(mixed $value, float $default, string $key): float
    {
        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Full-text source config {$key} must be numeric.");
        }

        return (float) $value;
    }

    private static function intOrDefault(mixed $value, int $default, string $key): int
    {
        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Full-text source config {$key} must be an integer.");
        }

        return (int) $value;
    }

    private static function boolOrDefault(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }
}
