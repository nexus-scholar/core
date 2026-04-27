<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\RateLimit;

use Nexus\Search\Infrastructure\RateLimit\TokenBucketRateLimiter;

it('allows immediate first request', function () {
    $limiter = new TokenBucketRateLimiter(ratePerSecond: 10.0, capacity: 10.0);
    
    $start = hrtime(true);
    $limiter->waitForToken();
    $end = hrtime(true);
    
    // Should be almost instantaneous (< 5ms)
    $elapsedMs = ($end - $start) / 1_000_000;
    expect($elapsedMs)->toBeLessThan(5.0);
});

it('blocks until token is available', function () {
    // 10 tokens per second means 100ms per token
    // Capacity 1 means we start with 1 token, next one takes 100ms
    $limiter = new TokenBucketRateLimiter(ratePerSecond: 10.0, capacity: 1.0);
    
    // First one is instant
    $limiter->waitForToken();
    
    $start = hrtime(true);
    // Second one should wait ~100ms
    $limiter->waitForToken();
    $end = hrtime(true);
    
    $elapsedMs = ($end - $start) / 1_000_000;
    
    // allow some buffer for scheduling overhead: 90ms to 150ms
    expect($elapsedMs)->toBeGreaterThanOrEqual(90.0);
    expect($elapsedMs)->toBeLessThan(150.0);
});

it('returns false from try consume when empty', function () {
    $limiter = new TokenBucketRateLimiter(ratePerSecond: 10.0, capacity: 1.0);
    
    // Consume the initial 1 token
    expect($limiter->tryConsume())->toBeTrue();
    
    // Second attempt should fail immediately
    expect($limiter->tryConsume())->toBeFalse();
});

it('returns true from try consume when token available', function () {
    $limiter = new TokenBucketRateLimiter(ratePerSecond: 10.0, capacity: 2.0);
    
    // Start with 2 tokens, can consume both instantly
    expect($limiter->tryConsume())->toBeTrue();
    expect($limiter->tryConsume())->toBeTrue();
    
    // Third attempt fails
    expect($limiter->tryConsume())->toBeFalse();
});

it('accumulates tokens over time up to capacity', function () {
    // Rate: 10/s. Capacity: 2.0. So 100ms per token.
    $limiter = new TokenBucketRateLimiter(ratePerSecond: 10.0, capacity: 2.0);
    
    // Empty the bucket
    $limiter->tryConsume();
    $limiter->tryConsume();
    expect($limiter->tryConsume())->toBeFalse();
    
    // Sleep for 150ms. Should accumulate 1.5 tokens (1 available)
    usleep(150_000);
    
    expect($limiter->tryConsume())->toBeTrue();
    expect($limiter->tryConsume())->toBeFalse();
});

it('does not accumulate beyond capacity', function () {
    // Rate: 10/s. Capacity: 2.0.
    $limiter = new TokenBucketRateLimiter(ratePerSecond: 10.0, capacity: 2.0);
    
    // Empty it
    $limiter->tryConsume();
    $limiter->tryConsume();
    expect($limiter->tryConsume())->toBeFalse();
    
    // Sleep for 500ms. Would accumulate 5 tokens, but capped at 2.
    usleep(500_000);
    
    expect($limiter->tryConsume())->toBeTrue();
    expect($limiter->tryConsume())->toBeTrue();
    expect($limiter->tryConsume())->toBeFalse(); // 3rd should fail
});
