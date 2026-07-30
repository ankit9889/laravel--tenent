<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantConfigManagerInterface;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantResolverInterface;
use App\Tenant\Managers\TenantCacheManager;
use App\Tenant\Managers\TenantConfigManager;
use App\Tenant\Managers\TenantDistributedLockManager;
use App\Tenant\Managers\TenantFilesystemManager;
use App\Tenant\Managers\TenantRateLimiter;
use App\Tenant\Managers\TenantRedisPrefixManager;
use App\Tenant\Schedule\TenantScheduleManager;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Redis\RedisManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

final class TenantRuntimeManagersTest extends MockeryTestCase
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

    public function test_cache_manager_applies_tenant_tag_and_prefix(): void
    {
        $cache = Mockery::mock(CacheManager::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $store = Mockery::mock(Repository::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $cache->shouldReceive('store')->andReturn($store);

        $store->shouldReceive('tags')->with(['tenant:uuid-1', 'users'])->once()->andReturn($store);

        $manager = new TenantCacheManager($cache, $context);
        $manager->tags(['users']);

        $this->assertSame('t_1:', $manager->getTenantCachePrefix());
    }

    public function test_filesystem_manager_returns_tenant_storage_prefix(): void
    {
        $fs = Mockery::mock(FilesystemManager::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $disk = Mockery::mock(Filesystem::class);

        $fs->shouldReceive('getDefaultDriver')->andReturn('local');
        $fs->shouldReceive('disk')->with('local')->andReturn($disk);
        $context->shouldReceive('getOrNull')->andReturn($this->tenant);

        $manager = new TenantFilesystemManager($fs, $context);

        $this->assertSame('t_1', $manager->getStoragePrefix());
        $this->assertSame($disk, $manager->disk());
    }

    public function test_config_manager_applies_and_reverts_runtime_overrides(): void
    {
        $config = Mockery::mock(ConfigRepository::class);

        $config->shouldReceive('get')->with('app.timezone')->andReturn('UTC');
        $config->shouldReceive('get')->with('cache.prefix')->andReturn('laravel_cache:');
        $config->shouldReceive('get')->with('filesystems.default')->andReturn('local');

        $config->shouldReceive('set')->with('app.timezone', 'UTC')->twice();
        $config->shouldReceive('set')->with('cache.prefix', 't_1:')->once();
        $config->shouldReceive('set')->with('cache.prefix', 'laravel_cache:')->once();
        $config->shouldReceive('set')->with('filesystems.default', 'local')->once();

        $manager = new TenantConfigManager($config);
        $manager->apply($this->tenant);
        $manager->revert();

        $this->assertTrue(true);
    }

    public function test_redis_prefix_manager_computes_scoped_key(): void
    {
        $redis = Mockery::mock(RedisManager::class);
        $context = Mockery::mock(TenantContextInterface::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);

        $manager = new TenantRedisPrefixManager($redis, $context);

        $this->assertSame('t_1:orders', $manager->key('orders'));
    }

    public function test_distributed_lock_manager_scopes_lock_name(): void
    {
        $cache = Mockery::mock(CacheManager::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $store = Mockery::mock(CacheRepository::class, LockProvider::class);
        $lock = Mockery::mock(Lock::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $cache->shouldReceive('store')->andReturn($store);
        $store->shouldReceive('lock')->with('lock:tenant:uuid-1:invoice_1', 60, null)->once()->andReturn($lock);

        $manager = new TenantDistributedLockManager($cache, $context);
        $result = $manager->acquire('invoice_1');

        $this->assertSame($lock, $result);
    }

    public function test_rate_limiter_scopes_key_by_tenant_id(): void
    {
        $limiter = Mockery::mock(RateLimiter::class);
        $context = Mockery::mock(TenantContextInterface::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $limiter->shouldReceive('tooManyAttempts')->with('tenant:1:rate:api', 60)->once()->andReturn(false);

        $manager = new TenantRateLimiter($limiter, $context);

        $this->assertFalse($manager->tooManyAttempts('api', 60));
    }

    public function test_schedule_manager_executes_callback_for_all_active_tenants(): void
    {
        $resolver = Mockery::mock(TenantResolverInterface::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $dbManager = Mockery::mock(TenantDatabaseManagerInterface::class);
        $configManager = Mockery::mock(TenantConfigManagerInterface::class);
        $schedule = Mockery::mock(Schedule::class);

        $resolver->shouldReceive('resolveAllActive')->once()->andReturn([$this->tenant]);
        $context->shouldReceive('runAs')->with($this->tenant, Mockery::type('callable'))->once()->andReturnUsing(static fn ($t, $cb) => $cb());
        $dbManager->shouldReceive('switchConnection')->with($this->tenant)->once();
        $configManager->shouldReceive('apply')->with($this->tenant)->once();
        $dbManager->shouldReceive('purgeConnection')->once();
        $configManager->shouldReceive('revert')->once();

        $event = Mockery::mock(Event::class);
        $schedule->shouldReceive('call')->with(Mockery::type('callable'))->once()->andReturnUsing(static function ($cb) use ($event) {
            $cb();

            return $event;
        });

        $manager = new TenantScheduleManager($resolver, $context, $dbManager, $configManager);
        $executed = false;

        $manager->call($schedule, static function () use (&$executed): void {
            $executed = true;
        });

        $this->assertTrue($executed);
    }
}
