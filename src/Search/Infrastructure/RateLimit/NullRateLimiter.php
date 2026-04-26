<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\RateLimit;

use Nexus\Search\Domain\Port\RateLimiterPort;

final class NullRateLimiter implements RateLimiterPort
{
    public function waitForToken(): void
    {
        // No-op
    }

    public function tryConsume(): bool
    {
        return true;
    }

    public function ratePerSecond(): float
    {
        return 1000.0;
    }
}
