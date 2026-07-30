<?php

declare(strict_types=1);

namespace App\Tenant\Context;

/**
 * Immutable Data Transfer Object representing a resolved tenant configuration.
 */
readonly class TenantConfig
{
    public function __construct(
        public int $tenantId,
        public string $uuid,
        public string $companyName,
        public string $databaseName,
        public string $databaseHost,
        public int $databasePort,
        public string $databaseUsername,
        public string $databasePassword,
        public string $timezone,
        public string $cachePrefix,
        public string $storagePrefix,
        public bool $isActive
    ) {}

    /**
     * Reconstruct an instance from an associative array (e.g., Redis JSON cache payload).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $tenantId = self::toInt($payload['tenant_id'] ?? 0, 0);
        $uuid = self::toString($payload['uuid'] ?? '', '');
        $companyName = self::toString($payload['company_name'] ?? '', '');
        $databaseName = self::toString($payload['database_name'] ?? '', '');
        $databaseHost = self::toString($payload['database_host'] ?? '', '');
        $databasePort = self::toInt($payload['database_port'] ?? 3306, 3306);
        $databaseUsername = self::toString($payload['database_username'] ?? '', '');
        $databasePassword = self::toString($payload['database_password'] ?? '', '');
        $timezone = self::toString($payload['timezone'] ?? 'UTC', 'UTC');
        $cachePrefix = self::toString($payload['cache_prefix'] ?? null, "tenant_{$tenantId}:");
        $storagePrefix = self::toString($payload['storage_prefix'] ?? null, "tenants/{$uuid}");
        $isActive = (bool) ($payload['is_active'] ?? true);

        return new self(
            tenantId: $tenantId,
            uuid: $uuid,
            companyName: $companyName,
            databaseName: $databaseName,
            databaseHost: $databaseHost,
            databasePort: $databasePort,
            databaseUsername: $databaseUsername,
            databasePassword: $databasePassword,
            timezone: $timezone,
            cachePrefix: $cachePrefix,
            storagePrefix: $storagePrefix,
            isActive: $isActive
        );
    }

    private static function toInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return intval($value);
        }

        return $default;
    }

    private static function toString(mixed $value, string $default): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return strval($value);
        }

        return $default;
    }

    /**
     * Serialize to array for Redis caching or queue payload transmission.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'uuid' => $this->uuid,
            'company_name' => $this->companyName,
            'database_name' => $this->databaseName,
            'database_host' => $this->databaseHost,
            'database_port' => $this->databasePort,
            'database_username' => $this->databaseUsername,
            'database_password' => $this->databasePassword,
            'timezone' => $this->timezone,
            'cache_prefix' => $this->cachePrefix,
            'storage_prefix' => $this->storagePrefix,
            'is_active' => $this->isActive,
        ];
    }
}
