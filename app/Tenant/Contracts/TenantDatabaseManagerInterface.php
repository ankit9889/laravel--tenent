<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use App\Tenant\Context\TenantConfig;

interface TenantDatabaseManagerInterface
{
    /**
     * Switch the default database connection to the specified tenant's database.
     */
    public function switchConnection(TenantConfig $tenant): void;

    /**
     * Purge the tenant database connection and restore the default main database connection.
     */
    public function purgeConnection(): void;

    /**
     * Reconnect to the specified tenant's database connection after purging existing sockets.
     */
    public function reconnect(TenantConfig $tenant): void;

    /**
     * Execute a callback within a database transaction on the active tenant connection.
     *
     * @template TReturn
     *
     * @param  callable(self): TReturn  $callback
     * @return TReturn
     */
    public function transaction(callable $callback, int $attempts = 1): mixed;

    /**
     * Get the name of the main system database connection.
     */
    public function getDefaultConnectionName(): string;

    /**
     * Get the name of the tenant database connection.
     */
    public function getTenantConnectionName(): string;

    /**
     * Determine if a tenant database connection is currently active as the default connection.
     */
    public function isTenantConnectionActive(): bool;
}
