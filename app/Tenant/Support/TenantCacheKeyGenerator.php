<?php

declare(strict_types=1);

namespace App\Tenant\Support;

/**
 * Deterministic cache key generator for tenant resolver lookups and domain caching.
 */
readonly class TenantCacheKeyGenerator
{
    private const PREFIX_DOMAIN = 'tenant:resolver:domain:';

    private const PREFIX_ID = 'tenant:resolver:id:';

    private const PREFIX_UUID = 'tenant:resolver:uuid:';

    public static function forDomain(string $domain): string
    {
        return self::PREFIX_DOMAIN.strtolower(trim($domain));
    }

    public static function forId(int $tenantId): string
    {
        return self::PREFIX_ID.$tenantId;
    }

    public static function forUuid(string $uuid): string
    {
        return self::PREFIX_UUID.strtolower(trim($uuid));
    }
}
