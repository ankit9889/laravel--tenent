<?php

declare(strict_types=1);

namespace App\Tenant\Queue;

use App\Tenant\Contracts\TenantContextInterface;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

final class TenantQueueContext
{
    public function __construct(
        private readonly TenantContextInterface $context
    ) {}

    /**
     * Prepare an array of jobs for queue chaining by attaching the active tenant ID to each job.
     *
     * @param  array<int, object>  $jobs
     * @return array<int, object>
     */
    public function prepareChain(array $jobs): array
    {
        $tenant = $this->context->getOrNull();
        $tenantId = $tenant !== null ? $tenant->tenantId : null;

        if ($tenantId === null) {
            return $jobs;
        }

        foreach ($jobs as $job) {
            if ($job instanceof \stdClass || property_exists($job, 'tenantId')) {
                $job->tenantId = $tenantId;
            }
        }

        return $jobs;
    }

    /**
     * Dispatch a batch of jobs scoped to the current active tenant.
     *
     * @param  array<int, object>  $jobs
     */
    public function batch(array $jobs): PendingBatch
    {
        return Bus::batch($this->prepareChain($jobs));
    }
}
