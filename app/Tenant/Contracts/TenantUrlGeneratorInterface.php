<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

interface TenantUrlGeneratorInterface
{
    /**
     * Generate an absolute URL for a named route scoped to the active tenant's domain.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $name, array $parameters = [], bool $absolute = true): string;

    /**
     * Generate an absolute URL for a path scoped to the active tenant's domain.
     */
    public function url(string $path): string;

    /**
     * Get the base URL scheme and hostname for the active tenant.
     */
    public function getBaseUrl(): string;
}
