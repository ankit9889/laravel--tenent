<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantCacheManagerInterface;
use App\Tenant\Contracts\TenantContextInterface;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;

final class TenantCacheManager implements TenantCacheManagerInterface
{
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly TenantContextInterface $context
    ) {}

    public function getStore(): Repository
    {
        $store = $this->cacheManager->store();
        $tenant = $this->context->getOrNull();

        if ($tenant !== null && $store instanceof \Illuminate\Cache\Repository) {
            $tagged = $store->tags(["tenant:{$tenant->uuid}"]);

            return $tagged;
        }

        return $store;
    }

    public function tags(array $tags): Repository
    {
        $store = $this->cacheManager->store();
        $tenant = $this->context->getOrNull();

        if ($tenant !== null) {
            array_unshift($tags, "tenant:{$tenant->uuid}");
        }

        if ($store instanceof \Illuminate\Cache\Repository) {
            $tagged = $store->tags($tags);

            return $tagged;
        }

        return $store;
    }

    public function flushTenantCache(): void
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return;
        }

        $store = $this->cacheManager->store();

        if ($store instanceof \Illuminate\Cache\Repository) {
            $tagged = $store->tags(["tenant:{$tenant->uuid}"]);
            $tagged->flush();
        }
    }

    public function getTenantCachePrefix(): string
    {
        $tenant = $this->context->getOrNull();

        return $tenant !== null ? $tenant->cachePrefix : 'global:';
    }
}
