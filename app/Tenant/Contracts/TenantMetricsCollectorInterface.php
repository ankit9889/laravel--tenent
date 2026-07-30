<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

interface TenantMetricsCollectorInterface
{
    /**
     * Increment a metric counter for the active tenant.
     *
     * @param  array<string, mixed>  $tags
     */
    public function increment(string $metric, int $value = 1, array $tags = []): void;

    /**
     * Record a timing/gauge measurement in milliseconds for the active tenant.
     *
     * @param  array<string, mixed>  $tags
     */
    public function gauge(string $metric, float $value, array $tags = []): void;

    /**
     * Get summary metrics for the active tenant within a time window.
     *
     * @return array<string, mixed>
     */
    public function summary(int $tenantId, int $secondsBack = 3600): array;
}
