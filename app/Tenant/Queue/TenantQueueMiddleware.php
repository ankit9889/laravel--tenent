<?php

declare(strict_types=1);

namespace App\Tenant\Queue;

use App\Tenant\Contracts\TenantConfigManagerInterface;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantResolverInterface;
use Closure;

final class TenantQueueMiddleware
{
    public function __construct(
        private readonly TenantResolverInterface $resolver,
        private readonly TenantContextInterface $context,
        private readonly TenantDatabaseManagerInterface $databaseManager,
        private readonly TenantConfigManagerInterface $configManager
    ) {}

    /**
     * Process the queue job within the active tenant's context.
     *
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        $tenantId = $job->tenantId ?? null;

        if (is_int($tenantId)) {
            $tenant = $this->resolver->resolveById($tenantId);
            $this->context->set($tenant);
            $this->databaseManager->switchConnection($tenant);
            $this->configManager->apply($tenant);
        }

        try {
            $next($job);
        } finally {
            $this->databaseManager->purgeConnection();
            $this->configManager->revert();
            $this->context->forget();
        }
    }
}
