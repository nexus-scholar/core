<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

final class ProviderConfig
{
    public function __construct(
        public readonly string  $alias,
        public readonly string  $baseUrl,
        public readonly float   $ratePerSecond,
        public readonly int     $timeoutSeconds = 30,
        public readonly ?string $apiKey         = null,
        public readonly ?string $mailTo         = null,
        // MUST come from env — never hardcode in source (known bug #11)
        public readonly int     $maxRetries     = 3,
        public readonly bool    $enabled        = true,
    ) {
        if (trim($alias) === '') {
            throw new \InvalidArgumentException('Provider alias cannot be empty.');
        }

        if (trim($baseUrl) === '') {
            throw new \InvalidArgumentException("Provider {$alias} base URL cannot be empty.");
        }

        if ($ratePerSecond <= 0) {
            throw new \InvalidArgumentException("Provider {$alias} rate limit must be greater than zero.");
        }

        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException("Provider {$alias} timeout must be greater than zero.");
        }

        if ($maxRetries < 1) {
            throw new \InvalidArgumentException("Provider {$alias} max retries must be at least one.");
        }
    }
}
