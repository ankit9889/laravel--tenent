<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantMetricsCollectorInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class TenantMetricsCollector implements TenantMetricsCollectorInterface
{
    private const STATS_PREFIX = 'metrics:tenant:';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly TenantContextInterface $context
    ) {}

    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return;
        }

        $key = self::STATS_PREFIX."{$tenant->tenantId}:{$metric}";
        $current = $this->toInt($this->cache->get($key, 0));

        $this->cache->put($key, $current + $value, 86400);
    }

    public function gauge(string $metric, float $value, array $tags = []): void
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return;
        }

        $key = self::STATS_PREFIX."{$tenant->tenantId}:gauge:{$metric}";

        $this->cache->put($key, $value, 86400);
    }

    public function summary(int $tenantId, int $secondsBack = 3600): array
    {
        return [
            'tenant_id' => $tenantId,
            'requests_total' => $this->toInt($this->cache->get(self::STATS_PREFIX."{$tenantId}:http.requests", 0)),
            'queries_total' => $this->toInt($this->cache->get(self::STATS_PREFIX."{$tenantId}:db.queries", 0)),
            'jobs_total' => $this->toInt($this->cache->get(self::STATS_PREFIX."{$tenantId}:queue.jobs", 0)),
        ];
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? intval($value) : 0;
    }
}
