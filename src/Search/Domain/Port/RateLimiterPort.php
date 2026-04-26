<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

interface RateLimiterPort
{
    /**
     * Block the current process until a token is available, then consume it.
     * MUST be called before every outbound HTTP request in every provider.
     * This is the single most important contract in the system.
     */
    public function waitForToken(): void;

    /**
     * Non-blocking: consume a token only if one is available right now.
     * Returns true if consumed, false if the caller must wait.
     */
    public function tryConsume(): bool;

    /** Return the configured rate in requests per second. */
    public function ratePerSecond(): float;
}
