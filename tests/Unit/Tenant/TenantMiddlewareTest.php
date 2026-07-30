<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantConfigManagerInterface;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantResolverInterface;
use App\Tenant\Http\Middleware\ResolveTenantMiddleware;
use App\Tenant\Queue\TenantQueueContext;
use App\Tenant\Queue\TenantQueueMiddleware;
use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

final class TenantMiddlewareTest extends MockeryTestCase
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

    public function test_resolve_tenant_middleware_switches_context_and_config(): void
    {
        $resolver = Mockery::mock(TenantResolverInterface::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $dbManager = Mockery::mock(TenantDatabaseManagerInterface::class);
        $configManager = Mockery::mock(TenantConfigManagerInterface::class);

        $resolver->shouldReceive('resolveByDomain')->with('acme.localhost')->once()->andReturn($this->tenant);
        $context->shouldReceive('set')->with($this->tenant)->once();
        $dbManager->shouldReceive('switchConnection')->with($this->tenant)->once();
        $configManager->shouldReceive('apply')->with($this->tenant)->once();

        $middleware = new ResolveTenantMiddleware($resolver, $context, $dbManager, $configManager);
        $request = Request::create('http://acme.localhost/test');

        $response = $middleware->handle($request, static function (): Response {
            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
    }

    public function test_tenant_queue_middleware_manages_job_lifecycle(): void
    {
        $resolver = Mockery::mock(TenantResolverInterface::class);
        $context = Mockery::mock(TenantContextInterface::class);
        $dbManager = Mockery::mock(TenantDatabaseManagerInterface::class);
        $configManager = Mockery::mock(TenantConfigManagerInterface::class);

        $job = new stdClass;
        $job->tenantId = 1;

        $resolver->shouldReceive('resolveById')->with(1)->once()->andReturn($this->tenant);
        $context->shouldReceive('set')->with($this->tenant)->once();
        $dbManager->shouldReceive('switchConnection')->with($this->tenant)->once();
        $configManager->shouldReceive('apply')->with($this->tenant)->once();

        $dbManager->shouldReceive('purgeConnection')->once();
        $configManager->shouldReceive('revert')->once();
        $context->shouldReceive('forget')->once();

        $middleware = new TenantQueueMiddleware($resolver, $context, $dbManager, $configManager);
        $executed = false;

        $middleware->handle($job, static function () use (&$executed): void {
            $executed = true;
        });

        $this->assertTrue($executed);
    }

    public function test_tenant_queue_context_prepares_jobs_for_chaining(): void
    {
        $context = Mockery::mock(TenantContextInterface::class);
        $context->shouldReceive('getOrNull')->once()->andReturn($this->tenant);

        $queueContext = new TenantQueueContext($context);
        $job1 = new stdClass;
        $job2 = new stdClass;

        $prepared = $queueContext->prepareChain([$job1, $job2]);

        $this->assertSame(1, $prepared[0]->tenantId);
        $this->assertSame(1, $prepared[1]->tenantId);
    }
}
