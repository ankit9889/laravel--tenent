<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantFilesystemManagerInterface;
use App\Tenant\Contracts\TenantHealthCheckInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Throwable;

final class TenantHealthCheck implements TenantHealthCheckInterface
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly CacheRepository $cache,
        private readonly TenantDatabaseManagerInterface $databaseManager,
        private readonly TenantFilesystemManagerInterface $filesystem
    ) {}

    public function check(TenantConfig $tenant): array
    {
        $dbStart = microtime(true);
        $dbHealthy = $this->checkDatabase($tenant);
        $dbLatency = (microtime(true) - $dbStart) * 1000.0;

        $cacheStart = microtime(true);
        $cacheHealthy = $this->checkCache($tenant);
        $cacheLatency = (microtime(true) - $cacheStart) * 1000.0;

        $fsHealthy = $this->checkFilesystem($tenant);

        $isOverallHealthy = $dbHealthy && $cacheHealthy && $fsHealthy;

        return [
            'status' => $isOverallHealthy ? 'healthy' : 'unhealthy',
            'checks' => [
                'database' => [
                    'status' => $dbHealthy ? 'ok' : 'error',
                    'message' => $dbHealthy ? 'Database reachable' : 'Database connection failed',
                    'latency_ms' => $dbLatency,
                ],
                'cache' => [
                    'status' => $cacheHealthy ? 'ok' : 'error',
                    'message' => $cacheHealthy ? 'Cache reachable' : 'Cache read/write failed',
                    'latency_ms' => $cacheLatency,
                ],
                'filesystem' => [
                    'status' => $fsHealthy ? 'ok' : 'error',
                    'message' => $fsHealthy ? 'Filesystem writable' : 'Filesystem access failed',
                ],
            ],
        ];
    }

    public function checkDatabase(TenantConfig $tenant): bool
    {
        try {
            $this->databaseManager->switchConnection($tenant);
            $connection = $this->db->connection('tenant');

            $connection->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            $this->databaseManager->purgeConnection();
        }
    }

    public function checkCache(TenantConfig $tenant): bool
    {
        try {
            $key = "health:{$tenant->uuid}";
            $this->cache->put($key, 'ok', 10);
            $result = $this->cache->get($key);
            $this->cache->forget($key);

            return $result === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    public function checkFilesystem(TenantConfig $tenant): bool
    {
        try {
            $disk = $this->filesystem->disk();
            $path = $this->filesystem->getStoragePrefix().'/health.txt';
            $disk->put($path, 'ok');
            $exists = $disk->exists($path);
            $disk->delete($path);

            return $exists;
        } catch (Throwable) {
            return false;
        }
    }
}
