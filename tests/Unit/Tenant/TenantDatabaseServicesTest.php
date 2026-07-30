<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Database\TenantConnectionFailoverManager;
use App\Tenant\Database\TenantReadReplicaSelector;
use App\Tenant\Managers\TenantMigrationManager;
use App\Tenant\Managers\TenantSeederManager;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use RuntimeException;

final class TenantDatabaseServicesTest extends MockeryTestCase
{
    private TenantConfig $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = new TenantConfig(
            1,
            'uuid-1',
            'Corp One',
            'tenant_db_1',
            '127.0.0.1',
            3306,
            'user',
            'pass',
            'UTC',
            't_1:',
            't_1',
            true
        );
    }

    public function test_migration_manager_switches_and_runs_migrate(): void
    {
        $artisan = Mockery::mock(ConsoleKernel::class);
        $dbManager = Mockery::mock(TenantDatabaseManagerInterface::class);

        $dbManager->shouldReceive('switchConnection')->with($this->tenant)->once();
        $artisan->shouldReceive('call')->with('migrate', Mockery::type('array'))->once()->andReturn(0);
        $artisan->shouldReceive('output')->once()->andReturn("Migrating table\nDone");
        $dbManager->shouldReceive('purgeConnection')->once();

        $manager = new TenantMigrationManager($artisan, $dbManager);
        $result = $manager->migrate($this->tenant);

        $this->assertSame('Migrating table', $result[0]);
    }

    public function test_seeder_manager_runs_db_seed_command(): void
    {
        $artisan = Mockery::mock(ConsoleKernel::class);
        $dbManager = Mockery::mock(TenantDatabaseManagerInterface::class);

        $dbManager->shouldReceive('switchConnection')->with($this->tenant)->once();
        $artisan->shouldReceive('call')->with('db:seed', Mockery::on(static function (array $args): bool {
            return $args['--database'] === 'tenant' && $args['--class'] === 'Database\\Seeders\\TenantDatabaseSeeder';
        }))->once()->andReturn(0);
        $dbManager->shouldReceive('purgeConnection')->once();

        $manager = new TenantSeederManager($artisan, $dbManager);
        $manager->seed($this->tenant);

        $this->assertTrue(true);
    }

    public function test_read_replica_selector_avoids_unhealthy_hosts(): void
    {
        $selector = new TenantReadReplicaSelector;
        $hosts = ['10.0.0.1', '10.0.0.2', '10.0.0.3'];

        $selector->markUnhealthy('10.0.0.1');

        $selected = $selector->select($hosts);
        $this->assertSame('10.0.0.2', $selected);

        $selectedNext = $selector->select($hosts);
        $this->assertSame('10.0.0.3', $selectedNext);
    }

    public function test_connection_failover_manager_retries_dead_connection(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $manager = new TenantConnectionFailoverManager($db);

        $db->shouldReceive('disconnect')->with('tenant')->once();
        $db->shouldReceive('purge')->with('tenant')->once();
        $db->shouldReceive('reconnect')->with('tenant')->once();

        $attempts = 0;
        $result = $manager->executeWithFailover(static function () use (&$attempts): string {
            $attempts++;
            if ($attempts === 1) {
                throw new QueryException(
                    'tenant',
                    'SELECT 1',
                    [],
                    new RuntimeException('server has gone away')
                );
            }

            return 'success';
        });

        $this->assertSame('success', $result);
        $this->assertSame(2, $attempts);
    }

    public function test_connection_failover_manager_graceful_shutdown(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $connection = Mockery::mock(ConnectionInterface::class);

        $db->shouldReceive('connection')->with('tenant')->once()->andReturn($connection);
        $connection->shouldReceive('transactionLevel')->once()->andReturn(1);
        $connection->shouldReceive('rollBack')->with(0)->once();

        $db->shouldReceive('disconnect')->with('tenant')->once();
        $db->shouldReceive('purge')->with('tenant')->once();

        $manager = new TenantConnectionFailoverManager($db);
        $manager->gracefulShutdown('tenant');

        $this->assertTrue(true);
    }
}
