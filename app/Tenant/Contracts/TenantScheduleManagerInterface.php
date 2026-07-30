<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

interface TenantScheduleManagerInterface
{
    /**
     * Schedule an Artisan command to execute independently for every active tenant.
     *
     * @param  array<int|string, mixed>  $parameters
     */
    public function command(Schedule $schedule, string $command, array $parameters = []): Event;

    /**
     * Schedule a Closure to run within every active tenant's context.
     *
     * @param  callable(): void  $callback
     * @param  array<int|string, mixed>  $parameters
     */
    public function call(Schedule $schedule, callable $callback, array $parameters = []): Event;
}
