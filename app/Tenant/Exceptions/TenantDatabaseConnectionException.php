<?php

declare(strict_types=1);

namespace App\Tenant\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when configuring or switching to a tenant's database connection fails.
 */
final class TenantDatabaseConnectionException extends RuntimeException
{
    public static function forTenant(int $tenantId, string $databaseName, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Failed to establish database connection [%s] for tenant ID [%d].',
                $databaseName,
                $tenantId
            ),
            0,
            $previous
        );
    }
}
