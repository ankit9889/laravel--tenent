<?php

declare(strict_types=1);

namespace App\Tenant\Exceptions;

use RuntimeException;
use Throwable;

final class TenantDatabaseSwitchException extends RuntimeException
{
    public static function switchFailed(int $tenantId, string $databaseName, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Failed to switch database connection to [%s] for tenant ID [%d].',
                $databaseName,
                $tenantId
            ),
            0,
            $previous
        );
    }

    public static function transactionFailed(string $connectionName, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Database transaction failed on tenant connection [%s].', $connectionName),
            0,
            $previous
        );
    }
}
