<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

interface TenantFeatureFlagManagerInterface
{
    /**
     * Determine if a feature flag is enabled for the active tenant.
     */
    public function enabled(string $feature): bool;

    /**
     * Determine if a feature flag is disabled for the active tenant.
     */
    public function disabled(string $feature): bool;

    /**
     * Get all enabled feature flags for the active tenant.
     *
     * @return array<int, string>
     */
    public function all(): array;

    /**
     * Invalidate cached feature flags for a tenant.
     */
    public function invalidateCache(int $tenantId): void;
}
