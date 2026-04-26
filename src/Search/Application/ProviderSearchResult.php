<?php

declare(strict_types=1);

namespace Nexus\Search\Application;

final class ProviderSearchResult
{
    public function __construct(
        public readonly string  $providerAlias,
        public readonly int     $resultCount,
        public readonly bool    $success,
        public readonly ?string $error      = null,
        public readonly int     $durationMs = 0,
    ) {}
}
