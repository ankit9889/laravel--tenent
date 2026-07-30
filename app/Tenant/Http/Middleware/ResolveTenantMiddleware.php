<?php

declare(strict_types=1);

namespace App\Tenant\Http\Middleware;

use App\Tenant\Contracts\TenantConfigManagerInterface;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantResolverInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantMiddleware
{
    public function __construct(
        private readonly TenantResolverInterface $resolver,
        private readonly TenantContextInterface $context,
        private readonly TenantDatabaseManagerInterface $databaseManager,
        private readonly TenantConfigManagerInterface $configManager
    ) {}

    /**
     * Handle an incoming HTTP request by resolving and activating the tenant domain.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->getHost();
        $tenant = $this->resolver->resolveByDomain($domain);

        $this->context->set($tenant);
        $this->databaseManager->switchConnection($tenant);
        $this->configManager->apply($tenant);

        return $next($request);
    }
}
