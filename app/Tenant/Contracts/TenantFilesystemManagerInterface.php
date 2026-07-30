<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Illuminate\Contracts\Filesystem\Filesystem;

interface TenantFilesystemManagerInterface
{
    /**
     * Get a filesystem disk scoped to the current active tenant's storage prefix.
     */
    public function disk(?string $disk = null): Filesystem;

    /**
     * Get the absolute path prefix for the current active tenant on the specified disk.
     */
    public function getStoragePrefix(?string $disk = null): string;

    /**
     * Delete the entire storage directory for the current tenant (admin/cleanup only).
     */
    public function deleteTenantDirectory(?string $disk = null): bool;
}
