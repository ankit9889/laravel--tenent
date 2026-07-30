<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Illuminate\Contracts\Cache\Lock;

interface TenantDistributedLockManagerInterface
{
    /**
     * Acquire a distributed lock scoped to the active tenant.
     */
    public function acquire(string $name, int $seconds = 60, ?string $owner = null): Lock;

    /**
     * Execute a callback within a distributed lock scoped to the active tenant.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function block(string $name, int $seconds, callable $callback): mixed;

    /**
     * Release a lock by name scoped to the active tenant.
     */
    public function release(string $name): void;
}
