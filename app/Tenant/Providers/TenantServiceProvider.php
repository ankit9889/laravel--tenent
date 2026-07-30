<?php

declare(strict_types=1);

namespace App\Tenant\Providers;

use App\Tenant\Context\TenantContext;
use App\Tenant\Contracts\TenantAuditLoggerInterface;
use App\Tenant\Contracts\TenantBroadcastManagerInterface;
use App\Tenant\Contracts\TenantCacheManagerInterface;
use App\Tenant\Contracts\TenantConfigManagerInterface;
use App\Tenant\Contracts\TenantConnectionFailoverInterface;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use App\Tenant\Contracts\TenantDistributedLockManagerInterface;
use App\Tenant\Contracts\TenantExceptionReporterInterface;
use App\Tenant\Contracts\TenantFeatureFlagManagerInterface;
use App\Tenant\Contracts\TenantFilesystemManagerInterface;
use App\Tenant\Contracts\TenantHealthCheckInterface;
use App\Tenant\Contracts\TenantLoggerInterface;
use App\Tenant\Contracts\TenantMailManagerInterface;
use App\Tenant\Contracts\TenantMetricsCollectorInterface;
use App\Tenant\Contracts\TenantMigrationManagerInterface;
use App\Tenant\Contracts\TenantNotificationManagerInterface;
use App\Tenant\Contracts\TenantRateLimiterInterface;
use App\Tenant\Contracts\TenantReadReplicaSelectorInterface;
use App\Tenant\Contracts\TenantRedisPrefixManagerInterface;
use App\Tenant\Contracts\TenantResolverInterface;
use App\Tenant\Contracts\TenantScheduleManagerInterface;
use App\Tenant\Contracts\TenantSeederManagerInterface;
use App\Tenant\Contracts\TenantTracingInterface;
use App\Tenant\Contracts\TenantUrlGeneratorInterface;
use App\Tenant\Database\TenantConnectionFailoverManager;
use App\Tenant\Database\TenantDatabaseManager;
use App\Tenant\Database\TenantReadReplicaSelector;
use App\Tenant\Managers\TenantAuditLogger;
use App\Tenant\Managers\TenantBroadcastManager;
use App\Tenant\Managers\TenantCacheManager;
use App\Tenant\Managers\TenantConfigManager;
use App\Tenant\Managers\TenantDistributedLockManager;
use App\Tenant\Managers\TenantExceptionReporter;
use App\Tenant\Managers\TenantFeatureFlagManager;
use App\Tenant\Managers\TenantFilesystemManager;
use App\Tenant\Managers\TenantHealthCheck;
use App\Tenant\Managers\TenantLogger;
use App\Tenant\Managers\TenantMailManager;
use App\Tenant\Managers\TenantMetricsCollector;
use App\Tenant\Managers\TenantMigrationManager;
use App\Tenant\Managers\TenantNotificationManager;
use App\Tenant\Managers\TenantRateLimiter;
use App\Tenant\Managers\TenantRedisPrefixManager;
use App\Tenant\Managers\TenantSeederManager;
use App\Tenant\Managers\TenantTracing;
use App\Tenant\Managers\TenantUrlGenerator;
use App\Tenant\Resolvers\CachedDomainTenantResolver;
use App\Tenant\Schedule\TenantScheduleManager;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Log\LogManager;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

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
        $this->registerCoreFoundation();
        $this->registerRuntimeManagers();
        $this->registerSystemServices();
        $this->registerDatabaseServices();
        $this->registerAliases();
    }

    /**
     * Bootstrap lifecycle safety hooks for Octane and Horizon workers.
     */
    public function boot(): void
    {
        $this->registerLifecycleCleaners();
    }

    private function registerCoreFoundation(): void
    {
        $this->app->singleton(TenantContextInterface::class, static function (Application $app): TenantContextInterface {
            return new TenantContext;
        });

        $this->app->singleton(TenantResolverInterface::class, static function (Application $app): TenantResolverInterface {
            return new CachedDomainTenantResolver(
                $app->make(DatabaseManager::class),
                $app->make(Repository::class)
            );
        });

        $this->app->singleton(TenantDatabaseManagerInterface::class, static function (Application $app): TenantDatabaseManagerInterface {
            return new TenantDatabaseManager(
                $app->make(DatabaseManager::class),
                $app->make(\Illuminate\Contracts\Config\Repository::class)
            );
        });
    }

    private function registerRuntimeManagers(): void
    {
        $this->app->singleton(TenantCacheManagerInterface::class, static function (Application $app): TenantCacheManagerInterface {
            return new TenantCacheManager($app->make(CacheManager::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantFilesystemManagerInterface::class, static function (Application $app): TenantFilesystemManagerInterface {
            return new TenantFilesystemManager($app->make(FilesystemManager::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantConfigManagerInterface::class, static function (Application $app): TenantConfigManagerInterface {
            return new TenantConfigManager($app->make(\Illuminate\Contracts\Config\Repository::class));
        });

        $this->app->singleton(TenantRedisPrefixManagerInterface::class, static function (Application $app): TenantRedisPrefixManagerInterface {
            return new TenantRedisPrefixManager($app->make(RedisManager::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantDistributedLockManagerInterface::class, static function (Application $app): TenantDistributedLockManagerInterface {
            return new TenantDistributedLockManager($app->make(CacheManager::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantRateLimiterInterface::class, static function (Application $app): TenantRateLimiterInterface {
            return new TenantRateLimiter($app->make(RateLimiter::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantScheduleManagerInterface::class, static function (Application $app): TenantScheduleManagerInterface {
            return new TenantScheduleManager(
                $app->make(TenantResolverInterface::class),
                $app->make(TenantContextInterface::class),
                $app->make(TenantDatabaseManagerInterface::class),
                $app->make(TenantConfigManagerInterface::class)
            );
        });
    }

    private function registerSystemServices(): void
    {
        $this->app->singleton(TenantLoggerInterface::class, static function (Application $app): TenantLoggerInterface {
            return new TenantLogger($app->make(LogManager::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantUrlGeneratorInterface::class, static function (Application $app): TenantUrlGeneratorInterface {
            return new TenantUrlGenerator($app->make(UrlGenerator::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantMailManagerInterface::class, static function (Application $app): TenantMailManagerInterface {
            return new TenantMailManager($app->make(MailManager::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantNotificationManagerInterface::class, static function (Application $app): TenantNotificationManagerInterface {
            return new TenantNotificationManager;
        });

        $this->app->singleton(TenantBroadcastManagerInterface::class, static function (Application $app): TenantBroadcastManagerInterface {
            return new TenantBroadcastManager($app->make(BroadcastManager::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantFeatureFlagManagerInterface::class, static function (Application $app): TenantFeatureFlagManagerInterface {
            return new TenantFeatureFlagManager(
                $app->make(Repository::class),
                $app->make(DatabaseManager::class),
                $app->make(TenantContextInterface::class)
            );
        });

        $this->app->singleton(TenantAuditLoggerInterface::class, static function (Application $app): TenantAuditLoggerInterface {
            return new TenantAuditLogger(
                $app->make(DatabaseManager::class),
                $app->make(TenantContextInterface::class),
                $app->make(TenantDatabaseManagerInterface::class)
            );
        });

        $this->app->singleton(TenantHealthCheckInterface::class, static function (Application $app): TenantHealthCheckInterface {
            return new TenantHealthCheck(
                $app->make(DatabaseManager::class),
                $app->make(Repository::class),
                $app->make(TenantDatabaseManagerInterface::class),
                $app->make(TenantFilesystemManagerInterface::class)
            );
        });

        $this->app->singleton(TenantMetricsCollectorInterface::class, static function (Application $app): TenantMetricsCollectorInterface {
            return new TenantMetricsCollector($app->make(Repository::class), $app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantTracingInterface::class, static function (Application $app): TenantTracingInterface {
            return new TenantTracing($app->make(TenantContextInterface::class));
        });

        $this->app->singleton(TenantExceptionReporterInterface::class, static function (Application $app): TenantExceptionReporterInterface {
            return new TenantExceptionReporter(
                $app->make(ExceptionHandler::class),
                $app->make(TenantContextInterface::class),
                $app->make(TenantTracingInterface::class)
            );
        });
    }

    private function registerDatabaseServices(): void
    {
        $this->app->singleton(TenantMigrationManagerInterface::class, static function (Application $app): TenantMigrationManagerInterface {
            return new TenantMigrationManager(
                $app->make(Kernel::class),
                $app->make(TenantDatabaseManagerInterface::class)
            );
        });

        $this->app->singleton(TenantSeederManagerInterface::class, static function (Application $app): TenantSeederManagerInterface {
            return new TenantSeederManager(
                $app->make(Kernel::class),
                $app->make(TenantDatabaseManagerInterface::class)
            );
        });

        $this->app->singleton(TenantReadReplicaSelectorInterface::class, static function (Application $app): TenantReadReplicaSelectorInterface {
            return new TenantReadReplicaSelector;
        });

        $this->app->singleton(TenantConnectionFailoverInterface::class, static function (Application $app): TenantConnectionFailoverInterface {
            return new TenantConnectionFailoverManager($app->make(DatabaseManager::class));
        });
    }

    private function registerAliases(): void
    {
        $this->app->alias(TenantContextInterface::class, TenantContext::class);
        $this->app->alias(TenantResolverInterface::class, CachedDomainTenantResolver::class);
        $this->app->alias(TenantDatabaseManagerInterface::class, TenantDatabaseManager::class);
        $this->app->alias(TenantCacheManagerInterface::class, TenantCacheManager::class);
        $this->app->alias(TenantFilesystemManagerInterface::class, TenantFilesystemManager::class);
        $this->app->alias(TenantLoggerInterface::class, TenantLogger::class);
        $this->app->alias(TenantConfigManagerInterface::class, TenantConfigManager::class);
        $this->app->alias(TenantUrlGeneratorInterface::class, TenantUrlGenerator::class);
        $this->app->alias(TenantMailManagerInterface::class, TenantMailManager::class);
        $this->app->alias(TenantNotificationManagerInterface::class, TenantNotificationManager::class);
        $this->app->alias(TenantBroadcastManagerInterface::class, TenantBroadcastManager::class);
        $this->app->alias(TenantRedisPrefixManagerInterface::class, TenantRedisPrefixManager::class);
        $this->app->alias(TenantDistributedLockManagerInterface::class, TenantDistributedLockManager::class);
        $this->app->alias(TenantFeatureFlagManagerInterface::class, TenantFeatureFlagManager::class);
        $this->app->alias(TenantRateLimiterInterface::class, TenantRateLimiter::class);
        $this->app->alias(TenantAuditLoggerInterface::class, TenantAuditLogger::class);
        $this->app->alias(TenantHealthCheckInterface::class, TenantHealthCheck::class);
        $this->app->alias(TenantMigrationManagerInterface::class, TenantMigrationManager::class);
        $this->app->alias(TenantSeederManagerInterface::class, TenantSeederManager::class);
        $this->app->alias(TenantMetricsCollectorInterface::class, TenantMetricsCollector::class);
        $this->app->alias(TenantTracingInterface::class, TenantTracing::class);
        $this->app->alias(TenantExceptionReporterInterface::class, TenantExceptionReporter::class);
        $this->app->alias(TenantReadReplicaSelectorInterface::class, TenantReadReplicaSelector::class);
        $this->app->alias(TenantConnectionFailoverInterface::class, TenantConnectionFailoverManager::class);
        $this->app->alias(TenantScheduleManagerInterface::class, TenantScheduleManager::class);
    }

    /**
     * Prevent tenant state bleed across long-running workers (Octane / Queue / Supervisor).
     */
    private function registerLifecycleCleaners(): void
    {
        if (class_exists('Laravel\Octane\Events\RequestTerminated')) {
            Event::listen(
                [
                    'Laravel\Octane\Events\RequestTerminated',
                    'Laravel\Octane\Events\TaskTerminated',
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

        if ($this->app->bound(TenantConfigManagerInterface::class)) {
            /** @var TenantConfigManagerInterface $configManager */
            $configManager = $this->app->make(TenantConfigManagerInterface::class);
            $configManager->revert();
        }

        if ($this->app->bound(TenantContextInterface::class)) {
            /** @var TenantContextInterface $context */
            $context = $this->app->make(TenantContextInterface::class);
            $context->forget();
        }
    }
}
