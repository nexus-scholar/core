<?php

declare(strict_types=1);

namespace Tests\Support;

use Nexus\Search\Domain\Port\RateLimiterPort;

final class SpyRateLimiter implements RateLimiterPort
{
    public int $waits = 0;

    public function __construct(
        private readonly float $rate = 1.0,
    ) {}

    public function waitForToken(): void
    {
        $this->waits++;
    }

    public function tryConsume(): bool
    {
        return true;
    }

    public function ratePerSecond(): float
    {
        return $this->rate;
    }
}

