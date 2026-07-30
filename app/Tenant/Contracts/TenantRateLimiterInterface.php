<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

interface TenantRateLimiterInterface
{
    /**
     * Determine if the given key has exceeded the rate limit for the active tenant.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Increment the counter for a given key for the active tenant.
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    /**
     * Get the number of remaining attempts for the active tenant.
     */
    public function remaining(string $key, int $maxAttempts): int;

    /**
     * Clear the rate limiter attempts for a given key for the active tenant.
     */
    public function clear(string $key): void;
}
