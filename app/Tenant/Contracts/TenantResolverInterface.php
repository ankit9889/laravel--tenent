<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Exceptions\TenantCouldNotBeResolvedException;

/**
 * Contract for identifying and loading tenant configuration from domain, ID, or UUID.
 */
interface TenantResolverInterface
{
    /**
     * Resolve a tenant configuration by domain or subdomain.
     *
     * @throws TenantCouldNotBeResolvedException If domain is unregistered or inactive.
     */
    public function resolveByDomain(string $domain): TenantConfig;

    /**
     * Resolve a tenant configuration by its primary integer ID.
     *
     * @throws TenantCouldNotBeResolvedException If ID is not found or inactive.
     */
    public function resolveById(int $tenantId): TenantConfig;

    /**
     * Resolve a tenant configuration by its globally unique identifier (UUID).
     *
     * @throws TenantCouldNotBeResolvedException If UUID is not found or inactive.
     */
    public function resolveByUuid(string $uuid): TenantConfig;

    /**
     * Invalidate any cached representation of the tenant.
     */
    public function invalidateCache(int $tenantId, ?string $domain = null): void;
}
