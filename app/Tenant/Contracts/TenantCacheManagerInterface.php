<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Illuminate\Contracts\Cache\Repository;

interface TenantCacheManagerInterface
{
    /**
     * Get a cache repository instance scoped to the current active tenant.
     */
    public function getStore(): Repository;

    /**
     * Get a cache repository scoped to specific tags for the active tenant.
     *
     * @param  array<int, string>  $tags
     */
    public function tags(array $tags): Repository;

    /**
     * Flush all cache items belonging to the current active tenant.
     */
    public function flushTenantCache(): void;

    /**
     * Get the computed Redis cache key prefix for the current tenant.
     */
    public function getTenantCachePrefix(): string;
}
