<?php

declare(strict_types=1);

namespace App\Tenant\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant cannot be resolved from an execution context (e.g., domain, ID, or UUID).
 */
final class TenantCouldNotBeResolvedException extends RuntimeException
{
    public static function forDomain(string $domain): self
    {
        return new self(sprintf('Could not resolve an active tenant for domain [%s].', $domain));
    }

    public static function forId(int $tenantId): self
    {
        return new self(sprintf('Could not resolve an active tenant for ID [%d].', $tenantId));
    }

    public static function forUuid(string $uuid): self
    {
        return new self(sprintf('Could not resolve an active tenant for UUID [%s].', $uuid));
    }

    public static function contextUnset(): self
    {
        return new self('No active tenant has been resolved for the current execution context.');
    }
}
