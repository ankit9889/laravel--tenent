<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Context\TenantContext;
use App\Tenant\Exceptions\TenantCouldNotBeResolvedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TenantContextTest extends TestCase
{
    private TenantConfig $tenantA;

    private TenantConfig $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = new TenantConfig(
            1,
            'uuid-a',
            'Corp A',
            'db_a',
            'localhost',
            3306,
            'u',
            'p',
            'UTC',
            'a:',
            'a',
            true
        );

        $this->tenantB = new TenantConfig(
            2,
            'uuid-b',
            'Corp B',
            'db_b',
            'localhost',
            3306,
            'u',
            'p',
            'UTC',
            'b:',
            'b',
            true
        );
    }

    public function test_it_throws_exception_when_getting_unset_tenant(): void
    {
        $context = new TenantContext;

        $this->expectException(TenantCouldNotBeResolvedException::class);
        $context->get();
    }

    public function test_it_sets_and_retrieves_tenant_context(): void
    {
        $context = new TenantContext;
        $this->assertFalse($context->isResolved());
        $this->assertNull($context->getOrNull());

        $context->set($this->tenantA);

        $this->assertTrue($context->isResolved());
        $this->assertSame($this->tenantA, $context->get());
        $this->assertSame($this->tenantA, $context->getOrNull());
    }

    public function test_it_forgets_tenant(): void
    {
        $context = new TenantContext;
        $context->set($this->tenantA);
        $this->assertTrue($context->isResolved());

        $context->forget();

        $this->assertFalse($context->isResolved());
        $this->assertNull($context->getOrNull());
    }

    public function test_run_as_temporarily_switches_context_and_restores_previous(): void
    {
        $context = new TenantContext;
        $context->set($this->tenantA);

        $result = $context->runAs($this->tenantB, function () use ($context): string {
            $this->assertSame(2, $context->get()->tenantId);

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(1, $context->get()->tenantId);
    }

    public function test_run_as_restores_previous_context_even_when_exception_occurs(): void
    {
        $context = new TenantContext;
        $context->set($this->tenantA);

        try {
            $context->runAs($this->tenantB, static function (): void {
                throw new RuntimeException('Simulated failure during task');
            });
        } catch (RuntimeException) {
            // Expected exception
        }

        $this->assertSame(1, $context->get()->tenantId);
    }

    public function test_run_as_restores_unset_state_if_no_previous_tenant_was_active(): void
    {
        $context = new TenantContext;

        $context->runAs($this->tenantA, function () use ($context): void {
            $this->assertSame(1, $context->get()->tenantId);
        });

        $this->assertFalse($context->isResolved());
        $this->assertNull($context->getOrNull());
    }
}
