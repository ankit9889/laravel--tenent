<?php

declare(strict_types=1);

namespace App\Tenant\Resolvers;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantResolverInterface;
use App\Tenant\Exceptions\TenantCouldNotBeResolvedException;
use App\Tenant\Support\TenantCacheKeyGenerator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Redis-backed O(1) tenant resolver with multi-key cache warming and query-builder joins.
 */
final class CachedDomainTenantResolver implements TenantResolverInterface
{
    private const CACHE_TTL_SECONDS = 3600; // 1 hour

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly CacheRepository $cache
    ) {}

    public function resolveByDomain(string $domain): TenantConfig
    {
        $normalizedDomain = strtolower(trim($domain));
        $cacheKey = TenantCacheKeyGenerator::forDomain($normalizedDomain);

        return $this->resolveFromCacheOrDatabase(
            $cacheKey,
            fn () => $this->queryTenantByField('domains.domain', $normalizedDomain),
            fn () => TenantCouldNotBeResolvedException::forDomain($normalizedDomain)
        );
    }

    public function resolveById(int $tenantId): TenantConfig
    {
        $cacheKey = TenantCacheKeyGenerator::forId($tenantId);

        return $this->resolveFromCacheOrDatabase(
            $cacheKey,
            fn () => $this->queryTenantByField('clients.id', $tenantId),
            fn () => TenantCouldNotBeResolvedException::forId($tenantId)
        );
    }

    public function resolveByUuid(string $uuid): TenantConfig
    {
        $normalizedUuid = strtolower(trim($uuid));
        $cacheKey = TenantCacheKeyGenerator::forUuid($normalizedUuid);

        return $this->resolveFromCacheOrDatabase(
            $cacheKey,
            fn () => $this->queryTenantByField('clients.uuid', $normalizedUuid),
            fn () => TenantCouldNotBeResolvedException::forUuid($normalizedUuid)
        );
    }

    public function invalidateCache(int $tenantId, ?string $domain = null): void
    {
        $this->cache->forget(TenantCacheKeyGenerator::forId($tenantId));

        if ($domain !== null) {
            $this->cache->forget(TenantCacheKeyGenerator::forDomain($domain));
        }

        try {
            $record = $this->getMainConnection()
                ->table('clients')
                ->where('id', $tenantId)
                ->select('uuid')
                ->first();

            if ($record !== null && isset($record->uuid)) {
                $this->cache->forget(TenantCacheKeyGenerator::forUuid((string) $record->uuid));
            }
        } catch (Throwable) {
            // Suppress invalidation lookup error if DB is unreachable during cleanup
        }
    }

    /**
     * @param  callable(): ?array<string, mixed>  $databaseQuery
     * @param  callable(): TenantCouldNotBeResolvedException  $exceptionFactory
     *
     * @throws TenantCouldNotBeResolvedException
     */
    private function resolveFromCacheOrDatabase(
        string $cacheKey,
        callable $databaseQuery,
        callable $exceptionFactory
    ): TenantConfig {
        /** @var array<string, mixed>|null $cachedPayload */
        $cachedPayload = $this->cache->get($cacheKey);

        if (is_array($cachedPayload)) {
            return TenantConfig::fromArray($cachedPayload);
        }

        $payload = $databaseQuery();

        if ($payload === null) {
            throw $exceptionFactory();
        }

        $config = TenantConfig::fromArray($payload);

        // Store across all access vectors to optimize subsequent lookups
        $this->cache->put(
            TenantCacheKeyGenerator::forId($config->tenantId),
            $config->toArray(),
            self::CACHE_TTL_SECONDS
        );
        $this->cache->put(
            TenantCacheKeyGenerator::forUuid($config->uuid),
            $config->toArray(),
            self::CACHE_TTL_SECONDS
        );
        $this->cache->put(
            $cacheKey,
            $config->toArray(),
            self::CACHE_TTL_SECONDS
        );

        return $config;
    }

    /**
     * Query the main database for tenant and configuration metadata.
     *
     * @return array<string, mixed>|null
     */
    private function queryTenantByField(string $column, int|string $value): ?array
    {
        $query = $this->getMainConnection()
            ->table('clients')
            ->join('client_database_configs', 'clients.id', '=', 'client_database_configs.client_id')
            ->leftJoin('domains', 'clients.id', '=', 'domains.client_id')
            ->where('clients.status', 'active')
            ->where($column, $value)
            ->select([
                'clients.id as tenant_id',
                'clients.uuid',
                'clients.company_name',
                'clients.timezone',
                'clients.status',
                'client_database_configs.database_name',
                'client_database_configs.database_host',
                'client_database_configs.database_port',
                'client_database_configs.database_username',
                'client_database_configs.database_password',
                'client_database_configs.cache_prefix',
                'client_database_configs.storage_prefix',
            ]);

        $record = $query->first();

        if ($record === null) {
            return null;
        }

        return [
            'tenant_id' => (int) $record->tenant_id,
            'uuid' => (string) $record->uuid,
            'company_name' => (string) $record->company_name,
            'database_name' => (string) $record->database_name,
            'database_host' => (string) $record->database_host,
            'database_port' => (int) $record->database_port,
            'database_username' => (string) $record->database_username,
            'database_password' => (string) $record->database_password,
            'timezone' => (string) ($record->timezone ?? 'UTC'),
            'cache_prefix' => (string) ($record->cache_prefix ?? "tenant_{$record->tenant_id}:"),
            'storage_prefix' => (string) ($record->storage_prefix ?? "tenants/{$record->uuid}"),
            'is_active' => $record->status === 'active',
        ];
    }

    private function getMainConnection(): ConnectionInterface
    {
        return $this->databaseManager->connection('mysql');
    }
}
