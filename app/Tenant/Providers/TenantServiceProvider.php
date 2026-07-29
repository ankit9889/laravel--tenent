<?php

declare(strict_types=1);

namespace App\Tenant\Providers;

use App\Tenant\Context\TenantContext;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantResolverInterface;
use App\Tenant\Database\TenantDatabaseManager;
use App\Tenant\Resolvers\CachedDomainTenantResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskTerminated;

/**
 * Service Provider responsible for binding tenant infrastructure and managing Octane/Queue lifecycle isolation.
 */
final class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register tenant foundation bindings in the service container.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContextInterface::class, static function (Application $app): TenantContextInterface {
            return new TenantContext;
        });

        $this->app->singleton(TenantResolverInterface::class, static function (Application $app): TenantResolverInterface {
            return new CachedDomainTenantResolver(
                $app->make('db'),
                $app->make('cache.store')
            );
        });

        $this->app->singleton(TenantDatabaseManagerInterface::class, static function (Application $app): TenantDatabaseManagerInterface {
            return new TenantDatabaseManager(
                $app->make('db'),
                $app->make('config')
            );
        });

        $this->app->alias(TenantContextInterface::class, TenantContext::class);
        $this->app->alias(TenantResolverInterface::class, CachedDomainTenantResolver::class);
        $this->app->alias(TenantDatabaseManagerInterface::class, TenantDatabaseManager::class);
    }

    /**
     * Bootstrap lifecycle safety hooks for Octane and Horizon workers.
     */
    public function boot(): void
    {
        $this->registerLifecycleCleaners();
    }

    /**
     * Prevent tenant state bleed across long-running workers (Octane / Queue / Supervisor).
     */
    private function registerLifecycleCleaners(): void
    {
        if (class_exists(RequestTerminated::class)) {
            Event::listen(
                [
                    RequestTerminated::class,
                    TaskTerminated::class,
                ],
                function (): void {
                    $this->purgeTenantState();
                }
            );
        }

        Queue::before(function (JobProcessing $event): void {
            $this->purgeTenantState();
        });

        Queue::after(function (JobProcessed $event): void {
            $this->purgeTenantState();
        });

        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            $this->purgeTenantState();
        });

        $this->app->terminating(function (): void {
            $this->purgeTenantState();
        });
    }

    private function purgeTenantState(): void
    {
        if ($this->app->bound(TenantDatabaseManagerInterface::class)) {
            /** @var TenantDatabaseManagerInterface $databaseManager */
            $databaseManager = $this->app->make(TenantDatabaseManagerInterface::class);
            $databaseManager->purgeConnection();
        }

        if ($this->app->bound(TenantContextInterface::class)) {
            /** @var TenantContextInterface $context */
            $context = $this->app->make(TenantContextInterface::class);
            $context->forget();
        }
    }
}
