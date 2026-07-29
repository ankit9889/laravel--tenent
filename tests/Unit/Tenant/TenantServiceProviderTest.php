<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Context\TenantContext;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantResolverInterface;
use App\Tenant\Resolvers\CachedDomainTenantResolver;
use Tests\TestCase;

final class TenantServiceProviderTest extends TestCase
{
    public function test_it_registers_tenant_context_as_singleton(): void
    {
        $instanceA = $this->app->make(TenantContextInterface::class);
        $instanceB = $this->app->make(TenantContextInterface::class);

        $this->assertInstanceOf(TenantContext::class, $instanceA);
        $this->assertSame($instanceA, $instanceB);
    }

    public function test_it_registers_tenant_resolver_as_singleton(): void
    {
        $instanceA = $this->app->make(TenantResolverInterface::class);
        $instanceB = $this->app->make(TenantResolverInterface::class);

        $this->assertInstanceOf(CachedDomainTenantResolver::class, $instanceA);
        $this->assertSame($instanceA, $instanceB);
    }

    public function test_aliases_resolve_correctly(): void
    {
        $this->assertSame(
            $this->app->make(TenantContextInterface::class),
            $this->app->make(TenantContext::class)
        );

        $this->assertSame(
            $this->app->make(TenantResolverInterface::class),
            $this->app->make(CachedDomainTenantResolver::class)
        );
    }
}
