<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use App\Tenant\Context\TenantConfig;

interface TenantMigrationManagerInterface
{
    /**
     * Run migrations for a specific tenant.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    public function migrate(TenantConfig $tenant, array $options = []): array;

    /**
     * Rollback the last database migration operation for a specific tenant.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    public function rollback(TenantConfig $tenant, array $options = []): array;

    /**
     * Check if a tenant has pending unrun migrations.
     */
    public function hasPendingMigrations(TenantConfig $tenant): bool;
}
