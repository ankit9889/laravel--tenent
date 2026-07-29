<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Exceptions\TenantCouldNotBeResolvedException;

/**
 * Contract for managing runtime tenant state within an execution cycle.
 */
interface TenantContextInterface
{
    /**
     * Set the active tenant for the current execution cycle.
     */
    public function set(TenantConfig $tenant): void;

    /**
     * Retrieve the active tenant configuration.
     *
     * @throws TenantCouldNotBeResolvedException If no tenant is currently active.
     */
    public function get(): TenantConfig;

    /**
     * Retrieve the active tenant configuration or null if unset.
     */
    public function getOrNull(): ?TenantConfig;

    /**
     * Determine if a tenant is currently set and active.
     */
    public function isResolved(): bool;

    /**
     * Flush and reset the current tenant context (essential for Octane & Horizon worker loops).
     */
    public function forget(): void;

    /**
     * Execute a callback within the context of a specific tenant, restoring the previous state afterward.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(TenantConfig $tenant, callable $callback): mixed;
}
