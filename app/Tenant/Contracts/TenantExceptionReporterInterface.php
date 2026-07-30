<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Throwable;

interface TenantExceptionReporterInterface
{
    /**
     * Report an exception enriched with active tenant context and distributed trace metadata.
     *
     * @param  array<string, mixed>  $extraContext
     */
    public function report(Throwable $exception, array $extraContext = []): void;

    /**
     * Get the tenant context payload formatted for error tracking services.
     *
     * @return array<string, mixed>
     */
    public function getContextPayload(): array;
}
