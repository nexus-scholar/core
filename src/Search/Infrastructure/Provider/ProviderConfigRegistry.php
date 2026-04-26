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
                baseUrl:       'http://export.arxiv.org/api',
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
}
