<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\RateLimit;

use Nexus\Search\Domain\Port\RateLimiterPort;

final class TokenBucketRateLimiter implements RateLimiterPort
{
    private float $tokens;
    private float $lastRefillTimeNs;

    public function __construct(
        private readonly float $ratePerSecond,
        private readonly float $capacity,
    ) {
        $this->tokens = $capacity; // Start full
        $this->lastRefillTimeNs = (float) hrtime(true);
    }

    public function waitForToken(): void
    {
        $this->refill();

        if ($this->tokens < 1.0) {
            $tokensNeeded = 1.0 - $this->tokens;
            $sleepSeconds = $tokensNeeded / $this->ratePerSecond;
            $sleepMicroseconds = (int) ($sleepSeconds * 1_000_000);
            
            if ($sleepMicroseconds > 0) {
                usleep($sleepMicroseconds);
            }
            
            // After sleeping, we assume we have 1 token.
            // Update the last refill time so we don't double-count the time we just slept.
            $this->tokens = 1.0;
            $this->lastRefillTimeNs = (float) hrtime(true);
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
        $nowNs = (float) hrtime(true);
        $elapsedSeconds = ($nowNs - $this->lastRefillTimeNs) / 1_000_000_000;
        
        $this->tokens += $elapsedSeconds * $this->ratePerSecond;
        if ($this->tokens > $this->capacity) {
            $this->tokens = $this->capacity;
        }
        
        $this->lastRefillTimeNs = $nowNs;
    }
}
