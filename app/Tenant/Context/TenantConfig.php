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
        return new self(
            tenantId: (int) $payload['tenant_id'],
            uuid: (string) $payload['uuid'],
            companyName: (string) $payload['company_name'],
            databaseName: (string) $payload['database_name'],
            databaseHost: (string) $payload['database_host'],
            databasePort: (int) $payload['database_port'],
            databaseUsername: (string) $payload['database_username'],
            databasePassword: (string) $payload['database_password'],
            timezone: (string) ($payload['timezone'] ?? 'UTC'),
            cachePrefix: (string) ($payload['cache_prefix'] ?? "tenant_{$payload['tenant_id']}:"),
            storagePrefix: (string) ($payload['storage_prefix'] ?? "tenants/{$payload['uuid']}"),
            isActive: (bool) ($payload['is_active'] ?? true),
        );
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
