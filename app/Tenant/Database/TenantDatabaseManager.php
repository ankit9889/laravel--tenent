<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Exceptions\TenantDatabaseSwitchException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Throwable;

final class TenantDatabaseManager implements TenantDatabaseManagerInterface
{
    private const TENANT_CONNECTION_NAME = 'tenant';

    private const DEFAULT_CONNECTION_NAME = 'mysql';

    private bool $isActive = false;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly ConfigRepository $config
    ) {}

    public function switchConnection(TenantConfig $tenant): void
    {
        try {
            $this->purgeConnection();

            $baseConfigRaw = $this->config->get('database.connections.'.self::DEFAULT_CONNECTION_NAME, []);
            /** @var array<string, mixed> $baseConfig */
            $baseConfig = is_array($baseConfigRaw) ? $baseConfigRaw : [];
            $tenantConnectionConfig = $this->buildTenantConnectionConfig($baseConfig, $tenant);

            $this->config->set('database.connections.'.self::TENANT_CONNECTION_NAME, $tenantConnectionConfig);

            $this->databaseManager->purge(self::TENANT_CONNECTION_NAME);
            $this->databaseManager->setDefaultConnection(self::TENANT_CONNECTION_NAME);
            $this->databaseManager->reconnect(self::TENANT_CONNECTION_NAME);

            $this->isActive = true;
        } catch (Throwable $exception) {
            $this->isActive = false;
            $this->databaseManager->setDefaultConnection(self::DEFAULT_CONNECTION_NAME);

            throw TenantDatabaseSwitchException::switchFailed(
                $tenant->tenantId,
                $tenant->databaseName,
                $exception
            );
        }
    }

    public function purgeConnection(): void
    {
        $this->databaseManager->disconnect(self::TENANT_CONNECTION_NAME);
        $this->databaseManager->purge(self::TENANT_CONNECTION_NAME);
        $this->databaseManager->setDefaultConnection(self::DEFAULT_CONNECTION_NAME);

        $this->config->set('database.connections.'.self::TENANT_CONNECTION_NAME, null);
        $this->isActive = false;
    }

    public function reconnect(TenantConfig $tenant): void
    {
        $this->purgeConnection();
        $this->switchConnection($tenant);
    }

    /**
     * @template TReturn
     *
     * @param  callable(self): TReturn  $callback
     * @return TReturn
     */
    public function transaction(callable $callback, int $attempts = 1): mixed
    {
        try {
            return $this->databaseManager
                ->connection(self::TENANT_CONNECTION_NAME)
                ->transaction(fn () => $callback($this), $attempts);
        } catch (Throwable $exception) {
            throw TenantDatabaseSwitchException::transactionFailed(self::TENANT_CONNECTION_NAME, $exception);
        }
    }

    public function getDefaultConnectionName(): string
    {
        return self::DEFAULT_CONNECTION_NAME;
    }

    public function getTenantConnectionName(): string
    {
        return self::TENANT_CONNECTION_NAME;
    }

    public function isTenantConnectionActive(): bool
    {
        return $this->isActive && $this->databaseManager->getDefaultConnection() === self::TENANT_CONNECTION_NAME;
    }

    /**
     * @param  array<string, mixed>  $baseConfig
     * @return array<string, mixed>
     */
    private function buildTenantConnectionConfig(array $baseConfig, TenantConfig $tenant): array
    {
        $tenantConfig = array_merge($baseConfig, [
            'driver' => 'mysql',
            'host' => $tenant->databaseHost,
            'port' => $tenant->databasePort,
            'database' => $tenant->databaseName,
            'username' => $tenant->databaseUsername,
            'password' => $tenant->databasePassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'timezone' => $tenant->timezone,
        ]);

        if (str_contains($tenant->databaseHost, ',')) {
            $hosts = array_map('trim', explode(',', $tenant->databaseHost));
            $writeHost = array_shift($hosts);
            $readHosts = $hosts;

            $tenantConfig['read'] = [
                'host' => $readHosts,
            ];
            $tenantConfig['write'] = [
                'host' => [$writeHost],
            ];
            $tenantConfig['sticky'] = true;
            unset($tenantConfig['host']);
        }

        return $tenantConfig;
    }
}
