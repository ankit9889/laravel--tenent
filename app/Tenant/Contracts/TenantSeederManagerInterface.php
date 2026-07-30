<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use App\Tenant\Context\TenantConfig;

interface TenantSeederManagerInterface
{
    /**
     * Run a database seeder class against a specific tenant's database.
     */
    public function seed(TenantConfig $tenant, string $seederClass = 'Database\\Seeders\\TenantDatabaseSeeder'): void;
}
