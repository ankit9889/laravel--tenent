<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Database\TenantDatabaseManager;
use App\Tenant\Exceptions\TenantDatabaseSwitchException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use RuntimeException;

final class TenantDatabaseManagerTest extends MockeryTestCase
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
            'tenant_user',
            'secret',
            'UTC',
            't_1:',
            't_1',
            true
        );
    }

    public function test_it_switches_connection_and_mutates_config(): void
    {
        $dbMock = Mockery::mock(DatabaseManager::class);
        $configMock = Mockery::mock(ConfigRepository::class);

        $configMock->shouldReceive('set')->with('database.connections.tenant', null)->once();
        $configMock->shouldReceive('get')
            ->with('database.connections.mysql', [])
            ->once()
            ->andReturn(['driver' => 'mysql', 'charset' => 'utf8mb4']);

        $configMock->shouldReceive('set')
            ->with('database.connections.tenant', Mockery::on(static function (array $config): bool {
                return $config['database'] === 'tenant_db_1' && $config['host'] === '127.0.0.1';
            }))
            ->once();

        $dbMock->shouldReceive('disconnect')->with('tenant')->once();
        $dbMock->shouldReceive('purge')->with('tenant')->times(2);
        $dbMock->shouldReceive('setDefaultConnection')->with('mysql')->once();
        $dbMock->shouldReceive('setDefaultConnection')->with('tenant')->once();
        $dbMock->shouldReceive('reconnect')->with('tenant')->once();
        $dbMock->shouldReceive('getDefaultConnection')->andReturn('tenant');

        $manager = new TenantDatabaseManager($dbMock, $configMock);
        $manager->switchConnection($this->tenant);

        $this->assertTrue($manager->isTenantConnectionActive());
        $this->assertSame('tenant', $manager->getTenantConnectionName());
        $this->assertSame('mysql', $manager->getDefaultConnectionName());
    }

    public function test_it_purges_connection_and_restores_default(): void
    {
        $dbMock = Mockery::mock(DatabaseManager::class);
        $configMock = Mockery::mock(ConfigRepository::class);

        $dbMock->shouldReceive('disconnect')->with('tenant')->once();
        $dbMock->shouldReceive('purge')->with('tenant')->once();
        $dbMock->shouldReceive('setDefaultConnection')->with('mysql')->once();
        $dbMock->shouldReceive('getDefaultConnection')->andReturn('mysql');

        $configMock->shouldReceive('set')->with('database.connections.tenant', null)->once();

        $manager = new TenantDatabaseManager($dbMock, $configMock);
        $manager->purgeConnection();

        $this->assertFalse($manager->isTenantConnectionActive());
    }

    public function test_it_supports_read_write_replica_host_split(): void
    {
        $replicaTenant = new TenantConfig(
            2,
            'uuid-2',
            'Corp Replica',
            'tenant_db_2',
            '10.0.0.10,10.0.0.11,10.0.0.12',
            3306,
            'user',
            'pass',
            'UTC',
            't_2:',
            't_2',
            true
        );

        $dbMock = Mockery::mock(DatabaseManager::class);
        $configMock = Mockery::mock(ConfigRepository::class);

        $configMock->shouldReceive('set')->with('database.connections.tenant', null)->once();
        $configMock->shouldReceive('get')->with('database.connections.mysql', [])->once()->andReturn([]);
        $configMock->shouldReceive('set')
            ->with('database.connections.tenant', Mockery::on(static function (array $config): bool {
                return $config['write']['host'] === ['10.0.0.10'] &&
                       $config['read']['host'] === ['10.0.0.11', '10.0.0.12'] &&
                       $config['sticky'] === true &&
                       ! isset($config['host']);
            }))
            ->once();

        $dbMock->shouldReceive('disconnect')->with('tenant')->once();
        $dbMock->shouldReceive('purge')->with('tenant')->times(2);
        $dbMock->shouldReceive('setDefaultConnection')->with('mysql')->once();
        $dbMock->shouldReceive('setDefaultConnection')->with('tenant')->once();
        $dbMock->shouldReceive('reconnect')->with('tenant')->once();

        $manager = new TenantDatabaseManager($dbMock, $configMock);
        $manager->switchConnection($replicaTenant);
    }

    public function test_it_executes_callback_within_transaction(): void
    {
        $dbMock = Mockery::mock(DatabaseManager::class);
        $configMock = Mockery::mock(ConfigRepository::class);
        $connectionMock = Mockery::mock(ConnectionInterface::class);

        $dbMock->shouldReceive('connection')->with('tenant')->once()->andReturn($connectionMock);
        $connectionMock->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type('callable'), 1)
            ->andReturnUsing(static fn (callable $cb) => $cb());

        $manager = new TenantDatabaseManager($dbMock, $configMock);
        $result = $manager->transaction(static fn () => 'success');

        $this->assertSame('success', $result);
    }

    public function test_it_throws_exception_if_switch_connection_fails(): void
    {
        $dbMock = Mockery::mock(DatabaseManager::class);
        $configMock = Mockery::mock(ConfigRepository::class);

        $configMock->shouldReceive('set')->with('database.connections.tenant', null)->once();
        $configMock->shouldReceive('get')->once()->andReturn([]);
        $configMock->shouldReceive('set')->with('database.connections.tenant', Mockery::type('array'))->once();

        $dbMock->shouldReceive('disconnect')->with('tenant')->once();
        $dbMock->shouldReceive('purge')->with('tenant')->times(2);
        $dbMock->shouldReceive('setDefaultConnection')->with('mysql')->once();
        $dbMock->shouldReceive('setDefaultConnection')->with('tenant')->once();
        $dbMock->shouldReceive('reconnect')->with('tenant')->once()->andThrow(new RuntimeException('Connection refused'));
        $dbMock->shouldReceive('setDefaultConnection')->with('mysql')->once();

        $manager = new TenantDatabaseManager($dbMock, $configMock);

        $this->expectException(TenantDatabaseSwitchException::class);
        $manager->switchConnection($this->tenant);
    }
}
