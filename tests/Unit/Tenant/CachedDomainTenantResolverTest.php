<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Exceptions\TenantCouldNotBeResolvedException;
use App\Tenant\Resolvers\CachedDomainTenantResolver;
use App\Tenant\Support\TenantCacheKeyGenerator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

final class CachedDomainTenantResolverTest extends MockeryTestCase
{
    public function test_it_resolves_tenant_by_domain_from_redis_cache_without_querying_mysql(): void
    {
        $cacheMock = Mockery::mock(CacheRepository::class);
        $dbMock = Mockery::mock(DatabaseManager::class);

        $cachedPayload = [
            'tenant_id' => 10,
            'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            'company_name' => 'Acme Enterprise',
            'database_name' => 'tenant_10',
            'database_host' => '127.0.0.1',
            'database_port' => 3306,
            'database_username' => 'root',
            'database_password' => 'secret',
            'timezone' => 'UTC',
            'cache_prefix' => 't_10:',
            'storage_prefix' => 't_10',
            'is_active' => true,
        ];

        $cacheMock->shouldReceive('get')
            ->with(TenantCacheKeyGenerator::forDomain('acme.com'))
            ->once()
            ->andReturn($cachedPayload);

        $dbMock->shouldNotReceive('connection'); // Ensure MySQL is never called

        $resolver = new CachedDomainTenantResolver($dbMock, $cacheMock);
        $config = $resolver->resolveByDomain('  ACME.COM '); // Tests normalization

        $this->assertSame(10, $config->tenantId);
        $this->assertSame('tenant_10', $config->databaseName);
        $this->assertSame('acme.com', strtolower('acme.com'));
    }

    public function test_it_throws_exception_when_domain_not_found_in_cache_or_database(): void
    {
        $cacheMock = Mockery::mock(CacheRepository::class);
        $dbMock = Mockery::mock(DatabaseManager::class);
        $connectionMock = Mockery::mock(ConnectionInterface::class);
        $queryMock = Mockery::mock(Builder::class);

        $cacheMock->shouldReceive('get')
            ->with(TenantCacheKeyGenerator::forDomain('unknown.com'))
            ->once()
            ->andReturnNull();

        $dbMock->shouldReceive('connection')
            ->with('mysql')
            ->once()
            ->andReturn($connectionMock);

        $connectionMock->shouldReceive('table')
            ->with('clients')
            ->once()
            ->andReturn($queryMock);

        $queryMock->shouldReceive('join')->andReturnSelf();
        $queryMock->shouldReceive('leftJoin')->andReturnSelf();
        $queryMock->shouldReceive('where')->andReturnSelf();
        $queryMock->shouldReceive('select')->andReturnSelf();
        $queryMock->shouldReceive('first')->once()->andReturnNull();

        $resolver = new CachedDomainTenantResolver($dbMock, $cacheMock);

        $this->expectException(TenantCouldNotBeResolvedException::class);
        $resolver->resolveByDomain('unknown.com');
    }

    public function test_it_invalidates_cache_keys(): void
    {
        $cacheMock = Mockery::mock(CacheRepository::class);
        $dbMock = Mockery::mock(DatabaseManager::class);

        $cacheMock->shouldReceive('forget')
            ->with(TenantCacheKeyGenerator::forId(10))
            ->once();

        $cacheMock->shouldReceive('forget')
            ->with(TenantCacheKeyGenerator::forDomain('acme.com'))
            ->once();

        $resolver = new CachedDomainTenantResolver($dbMock, $cacheMock);
        $resolver->invalidateCache(10, 'acme.com');
    }
}
