<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\RateLimit;

use Nexus\Search\Domain\Port\RateLimiterPort;

/**
 * Token bucket rate limiter using hrtime(true) for nanosecond precision.
 * Uses hrtime() NOT microtime() — see known bug note in agent skill.
 */
final class TokenBucketRateLimiter implements RateLimiterPort
{
    private float $tokens;
    private float $lastRefillTime; // in seconds (from hrtime nanoseconds)

    public function __construct(
        private readonly float $ratePerSecond,
        private readonly float $capacity,
    ) {
        $this->tokens         = $capacity;
        $this->lastRefillTime = hrtime(true) / 1e9;
    }

    public function waitForToken(): void
    {
        $this->refill();

        if ($this->tokens < 1.0) {
            $waitSeconds = (1.0 - $this->tokens) / $this->ratePerSecond;
            // usleep expects microseconds
            usleep((int) ($waitSeconds * 1_000_000));
            $this->refill();
        }

        $this->tokens -= 1.0;
    }

    public function tryConsume(): bool
    {
        $this->refill();

        if ($this->tokens >= 1.0) {
            $this->tokens -= 1.0;

            return true;
        }

        return false;
    }

    public function ratePerSecond(): float
    {
        return $this->ratePerSecond;
    }

    private function refill(): void
    {
        $now     = hrtime(true) / 1e9;
        $elapsed = $now - $this->lastRefillTime;

        $this->tokens         = min(
            $this->capacity,
            $this->tokens + $elapsed * $this->ratePerSecond,
        );
        $this->lastRefillTime = $now;
    }
}
