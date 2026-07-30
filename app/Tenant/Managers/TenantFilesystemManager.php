<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantFilesystemManagerInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;

final class TenantFilesystemManager implements TenantFilesystemManagerInterface
{
    public function __construct(
        private readonly FilesystemManager $filesystem,
        private readonly TenantContextInterface $context
    ) {}

    public function disk(?string $disk = null): Filesystem
    {
        return $this->filesystem->disk($disk ?? $this->filesystem->getDefaultDriver());
    }

    public function getStoragePrefix(?string $disk = null): string
    {
        $tenant = $this->context->getOrNull();

        return $tenant !== null ? $tenant->storagePrefix : 'global';
    }

    public function deleteTenantDirectory(?string $disk = null): bool
    {
        $prefix = $this->getStoragePrefix($disk);

        if ($prefix === 'global') {
            return false;
        }

        return $this->disk($disk)->deleteDirectory($prefix);
    }
}
