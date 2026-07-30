<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantMigrationManagerInterface;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

final class TenantMigrationManager implements TenantMigrationManagerInterface
{
    public function __construct(
        private readonly ConsoleKernel $artisan,
        private readonly TenantDatabaseManagerInterface $databaseManager
    ) {}

    public function migrate(TenantConfig $tenant, array $options = []): array
    {
        try {
            $this->databaseManager->switchConnection($tenant);

            $parameters = array_merge([
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ], $options);

            $this->artisan->call('migrate', $parameters);

            return explode("\n", trim($this->artisan->output()));
        } finally {
            $this->databaseManager->purgeConnection();
        }
    }

    public function rollback(TenantConfig $tenant, array $options = []): array
    {
        try {
            $this->databaseManager->switchConnection($tenant);

            $parameters = array_merge([
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ], $options);

            $this->artisan->call('migrate:rollback', $parameters);

            return explode("\n", trim($this->artisan->output()));
        } finally {
            $this->databaseManager->purgeConnection();
        }
    }

    public function hasPendingMigrations(TenantConfig $tenant): bool
    {
        try {
            $this->databaseManager->switchConnection($tenant);

            $exitCode = $this->artisan->call('migrate:status', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
            ]);

            $output = $this->artisan->output();

            return $exitCode !== 0 || str_contains($output, 'Pending');
        } finally {
            $this->databaseManager->purgeConnection();
        }
    }
}
