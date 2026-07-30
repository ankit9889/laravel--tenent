<?php

declare(strict_types=1);

namespace App\Tenant\Schedule;

use App\Tenant\Contracts\TenantConfigManagerInterface;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantResolverInterface;
use App\Tenant\Contracts\TenantScheduleManagerInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

final class TenantScheduleManager implements TenantScheduleManagerInterface
{
    public function __construct(
        private readonly TenantResolverInterface $resolver,
        private readonly TenantContextInterface $context,
        private readonly TenantDatabaseManagerInterface $databaseManager,
        private readonly TenantConfigManagerInterface $configManager
    ) {}

    /**
     * @param  array<int|string, mixed>  $parameters
     */
    public function command(Schedule $schedule, string $command, array $parameters = []): Event
    {
        return $schedule->call(function () use ($command, $parameters): void {
            $tenants = $this->resolver->resolveAllActive();

            foreach ($tenants as $tenant) {
                $this->context->runAs($tenant, function () use ($tenant, $command, $parameters): void {
                    $this->databaseManager->switchConnection($tenant);
                    $this->configManager->apply($tenant);
                    try {
                        Artisan::call($command, $parameters);
                    } finally {
                        $this->databaseManager->purgeConnection();
                        $this->configManager->revert();
                    }
                });
            }
        });
    }

    /**
     * @param  array<int|string, mixed>  $parameters
     */
    public function call(Schedule $schedule, callable $callback, array $parameters = []): Event
    {
        return $schedule->call(function () use ($callback, $parameters): void {
            $tenants = $this->resolver->resolveAllActive();

            foreach ($tenants as $tenant) {
                $this->context->runAs($tenant, function () use ($tenant, $callback, $parameters): void {
                    $this->databaseManager->switchConnection($tenant);
                    $this->configManager->apply($tenant);
                    try {
                        $callback(...$parameters);
                    } finally {
                        $this->databaseManager->purgeConnection();
                        $this->configManager->revert();
                    }
                });
            }
        });
    }
}
