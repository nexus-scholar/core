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

    'providers' => [
        'ieee' => [
            'api_key' => env('NEXUS_IEEE_API_KEY'),
        ],
        
        'semantic_scholar' => [
            'api_key' => env('NEXUS_S2_API_KEY'),
        ],
        
        'pubmed' => [
            'api_key' => env('NEXUS_PUBMED_API_KEY'),
        ],

        // Used for polite pools in OpenAlex and Crossref
        'mail_to' => env('NEXUS_MAIL_TO', 'admin@example.com'),
    ],
];
