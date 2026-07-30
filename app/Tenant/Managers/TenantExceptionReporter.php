<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantExceptionReporterInterface;
use App\Tenant\Contracts\TenantTracingInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

final class TenantExceptionReporter implements TenantExceptionReporterInterface
{
    public function __construct(
        private readonly ExceptionHandler $exceptionHandler,
        private readonly TenantContextInterface $context,
        private readonly TenantTracingInterface $tracing
    ) {}

    public function report(Throwable $exception, array $extraContext = []): void
    {
        $payload = array_merge($this->getContextPayload(), $extraContext);

        $this->exceptionHandler->report($exception);
    }

    public function getContextPayload(): array
    {
        $tenant = $this->context->getOrNull();

        return [
            'tenant_id' => $tenant !== null ? $tenant->tenantId : null,
            'tenant_uuid' => $tenant !== null ? $tenant->uuid : null,
            'company_name' => $tenant !== null ? $tenant->companyName : null,
            'trace_id' => $this->tracing->getTraceId(),
        ];
    }
}
