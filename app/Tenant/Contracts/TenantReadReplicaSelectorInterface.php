<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

interface TenantReadReplicaSelectorInterface
{
    /**
     * Select the optimal read replica host from an array of configured replica hosts.
     *
     * @param  array<int, string>  $replicaHosts
     */
    public function select(array $replicaHosts): string;

    /**
     * Mark a replica host as unhealthy/unreachable so subsequent selections avoid it.
     */
    public function markUnhealthy(string $host): void;

    /**
     * Reset health status for all replica hosts.
     */
    public function resetHealth(): void;
}
