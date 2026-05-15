<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nexus Scholarly Provider Configurations
    |--------------------------------------------------------------------------
    |
    | API keys for external scholarly search providers.
    | Never hardcode these keys in version control; always use .env variables.
    |
    */

    'mail_to' => env('NEXUS_MAIL_TO', 'admin@example.com'),

    'providers' => [
        'openalex' => [
            'enabled' => env('NEXUS_OPENALEX_ENABLED', true),
            'rate_limit' => env('NEXUS_OPENALEX_RATE_LIMIT'),
            'timeout' => env('NEXUS_OPENALEX_TIMEOUT'),
            'max_retries' => env('NEXUS_OPENALEX_MAX_RETRIES'),
        ],

        'crossref' => [
            'enabled' => env('NEXUS_CROSSREF_ENABLED', true),
            'rate_limit' => env('NEXUS_CROSSREF_RATE_LIMIT'),
            'timeout' => env('NEXUS_CROSSREF_TIMEOUT'),
            'max_retries' => env('NEXUS_CROSSREF_MAX_RETRIES'),
        ],

        'semantic_scholar' => [
            'enabled' => env('NEXUS_S2_ENABLED', true),
            'api_key' => env('NEXUS_S2_API_KEY'),
            'rate_limit' => env('NEXUS_S2_RATE_LIMIT'),
            'timeout' => env('NEXUS_S2_TIMEOUT'),
            'max_retries' => env('NEXUS_S2_MAX_RETRIES'),
        ],

        'arxiv' => [
            'enabled' => env('NEXUS_ARXIV_ENABLED', true),
            'rate_limit' => env('NEXUS_ARXIV_RATE_LIMIT'),
            'timeout' => env('NEXUS_ARXIV_TIMEOUT'),
            'max_retries' => env('NEXUS_ARXIV_MAX_RETRIES'),
        ],

        'pubmed' => [
            'enabled' => env('NEXUS_PUBMED_ENABLED', true),
            'api_key' => env('NEXUS_PUBMED_API_KEY'),
            'rate_limit' => env('NEXUS_PUBMED_RATE_LIMIT'),
            'timeout' => env('NEXUS_PUBMED_TIMEOUT'),
            'max_retries' => env('NEXUS_PUBMED_MAX_RETRIES'),
        ],

        'ieee' => [
            // Null preserves the package default: disabled unless an API key is configured.
            'enabled' => env('NEXUS_IEEE_ENABLED'),
            'api_key' => env('NEXUS_IEEE_API_KEY'),
            'rate_limit' => env('NEXUS_IEEE_RATE_LIMIT'),
            'timeout' => env('NEXUS_IEEE_TIMEOUT'),
            'max_retries' => env('NEXUS_IEEE_MAX_RETRIES'),
        ],

        'doaj' => [
            'enabled' => env('NEXUS_DOAJ_ENABLED', true),
            'rate_limit' => env('NEXUS_DOAJ_RATE_LIMIT'),
            'timeout' => env('NEXUS_DOAJ_TIMEOUT'),
            'max_retries' => env('NEXUS_DOAJ_MAX_RETRIES'),
        ],
    ],

    'dissemination' => [
        'pdf_storage_disk' => env('NEXUS_PDF_DISK', 'public'),
    ],
];
