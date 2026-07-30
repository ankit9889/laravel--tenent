<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use App\Tenant\Context\TenantConfig;

interface TenantConfigManagerInterface
{
    /**
     * Apply the tenant's runtime configurations (timezone, cache prefix, filesystem root) to the global repository.
     */
    public function apply(TenantConfig $tenant): void;

    /**
     * Revert all tenant-specific configuration overrides to their system defaults.
     */
    public function revert(): void;

    /**
     * Get a tenant-scoped configuration value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a tenant-scoped configuration value for the current request cycle.
     */
    public function set(string $key, mixed $value): void;
}
