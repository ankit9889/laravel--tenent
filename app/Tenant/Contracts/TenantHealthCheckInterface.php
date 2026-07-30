<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use App\Tenant\Context\TenantConfig;

interface TenantHealthCheckInterface
{
    /**
     * Run a comprehensive health check on a specific tenant (database, cache, filesystem).
     *
     * @return array{status: 'healthy'|'unhealthy', checks: array<string, array{status: string, message: string, latency_ms?: float}>}
     */
    public function check(TenantConfig $tenant): array;

    /**
     * Check if the tenant's database connection is reachable and responsive.
     */
    public function checkDatabase(TenantConfig $tenant): bool;

    /**
     * Check if the tenant's Redis cache namespace is operational.
     */
    public function checkCache(TenantConfig $tenant): bool;

    /**
     * Check if the tenant's filesystem storage directory is writable.
     */
    public function checkFilesystem(TenantConfig $tenant): bool;
}
