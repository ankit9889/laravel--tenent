<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantFeatureFlagManagerInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;

final class TenantFeatureFlagManager implements TenantFeatureFlagManagerInterface
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly DatabaseManager $db,
        private readonly TenantContextInterface $context
    ) {}

    public function enabled(string $feature): bool
    {
        return in_array($feature, $this->all(), true);
    }

    public function disabled(string $feature): bool
    {
        return ! $this->enabled($feature);
    }

    public function all(): array
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return [];
        }

        $cacheKey = "tenant:{$tenant->tenantId}:features";

        /** @var array<int, string>|null $cached */
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        /** @var array<int, string> $features */
        $features = $this->db->connection('mysql')
            ->table('tenant_features')
            ->where('client_id', $tenant->tenantId)
            ->where('is_enabled', true)
            ->pluck('feature_key')
            ->toArray();

        $this->cache->put($cacheKey, $features, self::CACHE_TTL_SECONDS);

        return $features;
    }

    public function invalidateCache(int $tenantId): void
    {
        $this->cache->forget("tenant:{$tenantId}:features");
    }
}
