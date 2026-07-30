<?php

declare(strict_types=1);

namespace App\Tenant\Database;

use App\Tenant\Contracts\TenantReadReplicaSelectorInterface;
use InvalidArgumentException;

final class TenantReadReplicaSelector implements TenantReadReplicaSelectorInterface
{
    /**
     * @var array<string, bool>
     */
    private array $unhealthyHosts = [];

    private int $roundRobinIndex = 0;

    public function select(array $replicaHosts): string
    {
        if ($replicaHosts === []) {
            throw new InvalidArgumentException('Replica hosts array cannot be empty.');
        }

        $healthyHosts = array_values(array_filter(
            $replicaHosts,
            fn (string $host): bool => ! isset($this->unhealthyHosts[$host])
        ));

        if ($healthyHosts === []) {
            $this->resetHealth();
            $healthyHosts = array_values($replicaHosts);
        }

        $selected = $healthyHosts[$this->roundRobinIndex % count($healthyHosts)];
        $this->roundRobinIndex++;

        return $selected;
    }

    public function markUnhealthy(string $host): void
    {
        $this->unhealthyHosts[$host] = true;
    }

    public function resetHealth(): void
    {
        $this->unhealthyHosts = [];
    }
}
