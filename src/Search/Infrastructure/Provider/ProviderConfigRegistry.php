<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

/**
 * Factory for default provider configurations.
 * API keys and mail-to addresses MUST be injected from environment — never hardcoded.
 */
final class ProviderConfigRegistry
{
    /**
     * @return array<string, ProviderConfig> keyed by alias
     */
    public static function defaults(
        ?string $ieeeApiKey = null,
        ?string $s2ApiKey   = null,
        ?string $pubmedApiKey = null,
        ?string $mailTo     = null,
    ): array {
        return [
            'openalex' => new ProviderConfig(
                alias:         'openalex',
                baseUrl:       'https://api.openalex.org',
                ratePerSecond: 10.0,
                mailTo:        $mailTo,
            ),
            'crossref' => new ProviderConfig(
                alias:         'crossref',
                baseUrl:       'https://api.crossref.org',
                ratePerSecond: 15.0,
                mailTo:        $mailTo,
            ),
            'semantic_scholar' => new ProviderConfig(
                alias:         'semantic_scholar',
                baseUrl:       'https://api.semanticscholar.org',
                ratePerSecond: $s2ApiKey !== null ? 10.0 : 1.0,
                apiKey:        $s2ApiKey,
            ),
            'arxiv' => new ProviderConfig(
                alias:         'arxiv',
                baseUrl:       'https://export.arxiv.org/api',
                ratePerSecond: 3.0,
            ),
            'pubmed' => new ProviderConfig(
                alias:         'pubmed',
                baseUrl:       'https://eutils.ncbi.nlm.nih.gov/entrez/eutils',
                ratePerSecond: $pubmedApiKey !== null ? 10.0 : 3.0,
                apiKey:        $pubmedApiKey,
            ),
            'ieee' => new ProviderConfig(
                alias:         'ieee',
                baseUrl:       'https://ieeexploreapi.ieee.org/api/v1',
                ratePerSecond: 1.0,
                apiKey:        $ieeeApiKey,
                enabled:       $ieeeApiKey !== null,
            ),
            'doaj' => new ProviderConfig(
                alias:         'doaj',
                baseUrl:       'https://doaj.org/api',
                ratePerSecond: 5.0,
            ),
        ];
    }

    /**
     * Build provider configs from package/application config while preserving
     * default provider URLs and sensible public rate limits.
     *
     * @param array<string, array<string, mixed>> $providers
     * @return array<string, ProviderConfig> keyed by alias
     */
    public static function fromArray(array $providers, ?string $mailTo = null): array
    {
        $configs = self::defaults(
            ieeeApiKey: self::nullableString($providers['ieee']['api_key'] ?? null),
            s2ApiKey: self::nullableString($providers['semantic_scholar']['api_key'] ?? null),
            pubmedApiKey: self::nullableString($providers['pubmed']['api_key'] ?? null),
            mailTo: $mailTo,
        );

        foreach ($configs as $alias => $config) {
            $configs[$alias] = self::applyOverrides($config, $providers[$alias] ?? []);
        }

        return $configs;
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function applyOverrides(ProviderConfig $config, array $override): ProviderConfig
    {
        $alias = $config->alias;

        return new ProviderConfig(
            alias: $alias,
            baseUrl: self::stringOrDefault($override['base_url'] ?? null, $config->baseUrl, "{$alias}.base_url"),
            ratePerSecond: self::floatOrDefault(
                $override['rate_limit'] ?? $override['rate_per_second'] ?? null,
                $config->ratePerSecond,
                "{$alias}.rate_limit",
            ),
            timeoutSeconds: self::intOrDefault(
                $override['timeout'] ?? $override['timeout_seconds'] ?? null,
                $config->timeoutSeconds,
                "{$alias}.timeout",
            ),
            apiKey: array_key_exists('api_key', $override)
                ? self::nullableString($override['api_key'])
                : $config->apiKey,
            mailTo: array_key_exists('mail_to', $override)
                ? self::nullableString($override['mail_to'])
                : $config->mailTo,
            maxRetries: self::intOrDefault(
                $override['max_retries'] ?? $override['retries'] ?? null,
                $config->maxRetries,
                "{$alias}.max_retries",
            ),
            enabled: self::boolOrDefault($override['enabled'] ?? null, $config->enabled),
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
            throw new \InvalidArgumentException("Provider config {$key} cannot be empty.");
        }

        return $value;
    }

    private static function floatOrDefault(mixed $value, float $default, string $key): float
    {
        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Provider config {$key} must be numeric.");
        }

        return (float) $value;
    }

    private static function intOrDefault(mixed $value, int $default, string $key): int
    {
        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Provider config {$key} must be an integer.");
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
