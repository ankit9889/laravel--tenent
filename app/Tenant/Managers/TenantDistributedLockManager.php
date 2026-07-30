<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDistributedLockManagerInterface;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;

final class TenantDistributedLockManager implements TenantDistributedLockManagerInterface
{
    public function __construct(
        private readonly CacheManager $cache,
        private readonly TenantContextInterface $context
    ) {}

    public function acquire(string $name, int $seconds = 60, ?string $owner = null): Lock
    {
        $scopedName = $this->scopeLockName($name);
        $store = $this->cache->store();

        if ($store instanceof LockProvider) {
            return $store->lock($scopedName, $seconds, $owner);
        }

        throw new \RuntimeException('Current cache store does not support distributed locks.');
    }

    public function block(string $name, int $seconds, callable $callback): mixed
    {
        return $this->acquire($name, $seconds)->block($seconds, $callback);
    }

    public function release(string $name): void
    {
        $this->acquire($name)->release();
    }

    private function scopeLockName(string $name): string
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return "lock:global:{$name}";
        }

        return "lock:tenant:{$tenant->uuid}:{$name}";
    }
}
