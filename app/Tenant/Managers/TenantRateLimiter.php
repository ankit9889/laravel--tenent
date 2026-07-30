<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantRateLimiterInterface;
use Illuminate\Cache\RateLimiter;

final class TenantRateLimiter implements TenantRateLimiterInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly TenantContextInterface $context
    ) {}

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->limiter->tooManyAttempts($this->resolveKey($key), $maxAttempts);
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        return $this->limiter->hit($this->resolveKey($key), $decaySeconds);
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return $this->limiter->remaining($this->resolveKey($key), $maxAttempts);
    }

    public function clear(string $key): void
    {
        $this->limiter->clear($this->resolveKey($key));
    }

    private function resolveKey(string $key): string
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return "global:rate:{$key}";
        }

        return "tenant:{$tenant->tenantId}:rate:{$key}";
    }
}
