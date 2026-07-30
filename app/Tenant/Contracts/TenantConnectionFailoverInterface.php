<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Closure;

interface TenantConnectionFailoverInterface
{
    /**
     * Execute a callback with automatic database connection retry and failover recovery.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function executeWithFailover(Closure $callback, int $attempts = 3): mixed;

    /**
     * Detect if an exception indicates a dead or broken database connection.
     */
    public function isDeadConnectionException(\Throwable $exception): bool;

    /**
     * Recover a dead connection by purging the socket and re-initializing PDO.
     */
    public function recoverDeadConnection(string $connectionName = 'tenant'): void;

    /**
     * Perform graceful shutdown by flushing transactions and disconnecting open sockets.
     */
    public function gracefulShutdown(string $connectionName = 'tenant'): void;
}
