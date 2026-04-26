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
    ) {}
}
