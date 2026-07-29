<?php

declare(strict_types=1);

namespace App\Tenant\Context;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Exceptions\TenantCouldNotBeResolvedException;
use Throwable;

/**
 * Authoritative runtime repository for the active tenant's identity within an execution cycle.
 */
final class TenantContext implements TenantContextInterface
{
    private ?TenantConfig $currentTenant = null;

    public function set(TenantConfig $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    public function get(): TenantConfig
    {
        if ($this->currentTenant === null) {
            throw TenantCouldNotBeResolvedException::contextUnset();
        }

        return $this->currentTenant;
    }

    public function getOrNull(): ?TenantConfig
    {
        return $this->currentTenant;
    }

    public function isResolved(): bool
    {
        return $this->currentTenant !== null && $this->currentTenant->isActive;
    }

    public function forget(): void
    {
        $this->currentTenant = null;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws Throwable
     */
    public function runAs(TenantConfig $tenant, callable $callback): mixed
    {
        $previousTenant = $this->currentTenant;

        try {
            $this->set($tenant);

            return $callback();
        } finally {
            if ($previousTenant !== null) {
                $this->set($previousTenant);
            } else {
                $this->forget();
            }
        }
    }
}
