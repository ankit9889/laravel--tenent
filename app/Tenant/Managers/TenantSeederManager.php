<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantSeederManagerInterface;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

final class TenantSeederManager implements TenantSeederManagerInterface
{
    public function __construct(
        private readonly ConsoleKernel $artisan,
        private readonly TenantDatabaseManagerInterface $databaseManager
    ) {}

    public function seed(TenantConfig $tenant, string $seederClass = 'Database\\Seeders\\TenantDatabaseSeeder'): void
    {
        try {
            $this->databaseManager->switchConnection($tenant);

            $this->artisan->call('db:seed', [
                '--database' => 'tenant',
                '--class' => $seederClass,
                '--force' => true,
            ]);
        } finally {
            $this->databaseManager->purgeConnection();
        }
    }
}
