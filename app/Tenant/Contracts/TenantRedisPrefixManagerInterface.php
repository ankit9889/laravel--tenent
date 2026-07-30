<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

interface TenantRedisPrefixManagerInterface
{
    /**
     * Get the active tenant's Redis key prefix.
     */
    public function getPrefix(): string;

    /**
     * Apply the tenant's Redis prefix to a specific key.
     */
    public function key(string $name): string;

    /**
     * Configure a Redis connection to use the tenant's prefix.
     */
    public function applyPrefixToConnection(string $connection = 'default'): void;
}
