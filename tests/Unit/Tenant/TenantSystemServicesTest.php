<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantFilesystemManagerInterface;
use App\Tenant\Contracts\TenantTracingInterface;
use App\Tenant\Managers\TenantAuditLogger;
use App\Tenant\Managers\TenantBroadcastManager;
use App\Tenant\Managers\TenantExceptionReporter;
use App\Tenant\Managers\TenantFeatureFlagManager;
use App\Tenant\Managers\TenantHealthCheck;
use App\Tenant\Managers\TenantLogger;
use App\Tenant\Managers\TenantMailManager;
use App\Tenant\Managers\TenantMetricsCollector;
use App\Tenant\Managers\TenantTracing;
use App\Tenant\Managers\TenantUrlGenerator;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Log\LogManager;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Monolog\Level;
use Monolog\LogRecord;

final class TenantSystemServicesTest extends MockeryTestCase
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

    public function test_tenant_logger_processor_enriches_record(): void
    {
        $logManager = Mockery::mock(LogManager::class);
        $context = Mockery::mock(TenantContextInterface::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);

        $manager = new TenantLogger($logManager, $context);
        $processor = $manager->getProcessor();

        $record = new LogRecord(
            new \DateTimeImmutable,
            'test',
            Level::Info,
            'log message'
        );

        $processed = $processor($record);

        $this->assertSame(1, $processed->extra['tenant_id']);
        $this->assertSame('uuid-1', $processed->extra['tenant_uuid']);
    }

    public function test_url_generator_formats_tenant_base_url(): void
    {
        $urlGen = Mockery::mock(UrlGenerator::class);
        $context = Mockery::mock(TenantContextInterface::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $urlGen->shouldReceive('to')->with('/')->andReturn('https://localhost');
        $urlGen->shouldReceive('route')->with('dashboard', [], true)->andReturn('http://localhost/dashboard');

        $manager = new TenantUrlGenerator($urlGen, $context);

        $this->assertSame('https://uuid-1.localhost', $manager->getBaseUrl());
        $this->assertSame('http://uuid-1.localhost/dashboard', $manager->route('dashboard'));
    }

    public function test_mail_manager_enriches_mailable_with_tenant_data(): void
    {
        $mail = Mockery::mock(MailManager::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $mailable = Mockery::mock(Mailable::class);
        $mailer = Mockery::mock(Mailer::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $mail->shouldReceive('mailer')->andReturn($mailer);
        $mailable->shouldReceive('with')->with([
            'tenant_id' => 1,
            'tenant_uuid' => 'uuid-1',
            'company_name' => 'Corp One',
        ])->once()->andReturnSelf();
        $mailer->shouldReceive('send')->with($mailable)->once();

        $manager = new TenantMailManager($mail, $context);
        $manager->send($mailable);

        $this->assertTrue(true);
    }

    public function test_broadcast_manager_prefixes_channel_name(): void
    {
        $broadcast = Mockery::mock(BroadcastManager::class);
        $context = Mockery::mock(TenantContextInterface::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);

        $manager = new TenantBroadcastManager($broadcast, $context);

        $this->assertSame('tenant.uuid-1.orders', $manager->formatChannelName('orders'));
    }

    public function test_feature_flag_manager_checks_cached_features(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $db = Mockery::mock(DatabaseManager::class);
        $context = Mockery::mock(TenantContextInterface::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $cache->shouldReceive('get')->with('tenant:1:features')->andReturn(['billing_v2', 'api']);

        $manager = new TenantFeatureFlagManager($cache, $db, $context);

        $this->assertTrue($manager->enabled('api'));
        $this->assertFalse($manager->enabled('custom_reports'));
    }

    public function test_audit_logger_records_to_tenant_table(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $dbManager = Mockery::mock(TenantDatabaseManagerInterface::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $builder = Mockery::mock(Builder::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $dbManager->shouldReceive('isTenantConnectionActive')->andReturn(true);
        $db->shouldReceive('connection')->with('tenant')->andReturn($connection);
        $connection->shouldReceive('table')->with('tenant_audit_logs')->andReturn($builder);
        $builder->shouldReceive('insert')->once()->andReturn(true);

        $manager = new TenantAuditLogger($db, $context, $dbManager);
        $manager->log('user.created', 'User 10 added');

        $this->assertTrue(true);
    }

    public function test_health_check_returns_status(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $cache = Mockery::mock(CacheRepository::class);
        $dbManager = Mockery::mock(TenantDatabaseManagerInterface::class);
        $fs = Mockery::mock(TenantFilesystemManagerInterface::class);

        $dbManager->shouldReceive('switchConnection')->with($this->tenant)->once();
        $dbManager->shouldReceive('purgeConnection')->once();
        $db->shouldReceive('connection')->with('tenant')->andReturn(Mockery::mock(ConnectionInterface::class, static function ($mock): void {
            $mock->shouldReceive('select')->with('SELECT 1')->once()->andReturn([]);
        }));

        $cache->shouldReceive('put')->once();
        $cache->shouldReceive('get')->once()->andReturn('ok');
        $cache->shouldReceive('forget')->once();

        $fs->shouldReceive('disk')->andReturn(Mockery::mock(Filesystem::class, static function ($mock): void {
            $mock->shouldReceive('put')->once();
            $mock->shouldReceive('exists')->once()->andReturn(true);
            $mock->shouldReceive('delete')->once();
        }));
        $fs->shouldReceive('getStoragePrefix')->andReturn('t_1');

        $manager = new TenantHealthCheck($db, $cache, $dbManager, $fs);
        $result = $manager->check($this->tenant);

        $this->assertSame('healthy', $result['status']);
    }

    public function test_metrics_collector_increments_counter(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $context = Mockery::mock(TenantContextInterface::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $cache->shouldReceive('get')->with('metrics:tenant:1:db.queries', 0)->andReturn(5);
        $cache->shouldReceive('put')->with('metrics:tenant:1:db.queries', 6, 86400)->once();

        $manager = new TenantMetricsCollector($cache, $context);
        $manager->increment('db.queries');

        $this->assertTrue(true);
    }

    public function test_tracing_generates_uuid_and_enriches_attributes(): void
    {
        $context = Mockery::mock(TenantContextInterface::class);
        $context->shouldReceive('getOrNull')->andReturn($this->tenant);

        $manager = new TenantTracing($context);
        $traceId = $manager->getTraceId();

        $this->assertNotEmpty($traceId);

        $manager->span('test_op', function () use ($manager): void {
            $attrs = $manager->getCurrentSpanAttributes();
            $this->assertSame(1, $attrs['tenant_id']);
            $this->assertSame('uuid-1', $attrs['tenant_uuid']);
        });
    }

    public function test_exception_reporter_builds_context_payload(): void
    {
        $handler = Mockery::mock(ExceptionHandler::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $tracing = Mockery::mock(TenantTracingInterface::class);

        $context->shouldReceive('getOrNull')->andReturn($this->tenant);
        $tracing->shouldReceive('getTraceId')->andReturn('trace-123');

        $manager = new TenantExceptionReporter($handler, $context, $tracing);
        $payload = $manager->getContextPayload();

        $this->assertSame(1, $payload['tenant_id']);
        $this->assertSame('uuid-1', $payload['tenant_uuid']);
        $this->assertSame('trace-123', $payload['trace_id']);
    }
}
