<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use App\Tenant\Contracts\TenantConnectionFailoverInterface;
use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Throwable;

final class TenantConnectionFailoverManager implements TenantConnectionFailoverInterface
{
    /**
     * @var array<int, string>
     */
    private const DEAD_CONNECTION_MESSAGES = [
        'server has gone away',
        'no connection to the server',
        'lost connection',
        'is dead or not enabled',
        'error while sending',
        'decryption failed or bad record mac',
        'server closed the connection unexpectedly',
        'ssl connection has been closed unexpectedly',
        'deadlock found when trying to get lock',
        'connection refused',
    ];

    public function __construct(
        private readonly DatabaseManager $db
    ) {}

    public function executeWithFailover(Closure $callback, int $attempts = 3): mixed
    {
        $currentAttempt = 1;

        while (true) {
            try {
                return $callback();
            } catch (Throwable $exception) {
                if ($currentAttempt >= $attempts || ! $this->isDeadConnectionException($exception)) {
                    throw $exception;
                }

                $this->recoverDeadConnection('tenant');
                $currentAttempt++;
            }
        }
    }

    public function isDeadConnectionException(Throwable $exception): bool
    {
        if (! ($exception instanceof QueryException)) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        foreach (self::DEAD_CONNECTION_MESSAGES as $deadMessage) {
            if (str_contains($message, $deadMessage)) {
                return true;
            }
        }

        return false;
    }

    public function recoverDeadConnection(string $connectionName = 'tenant'): void
    {
        $this->db->disconnect($connectionName);
        $this->db->purge($connectionName);
        $this->db->reconnect($connectionName);
    }

    public function gracefulShutdown(string $connectionName = 'tenant'): void
    {
        try {
            $connection = $this->db->connection($connectionName);
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack(0);
            }
        } catch (Throwable) {
            // Ignore transaction check failure if socket is already unreachable
        } finally {
            $this->db->disconnect($connectionName);
            $this->db->purge($connectionName);
        }
    }
}
