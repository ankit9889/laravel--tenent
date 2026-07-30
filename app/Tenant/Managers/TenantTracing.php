<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantTracingInterface;
use Illuminate\Support\Str;

final class TenantTracing implements TenantTracingInterface
{
    private ?string $currentTraceId = null;

    /**
     * @var array<string, mixed>
     */
    private array $currentSpanAttributes = [];

    public function __construct(
        private readonly TenantContextInterface $context
    ) {}

    public function getTraceId(): string
    {
        if ($this->currentTraceId === null) {
            $this->currentTraceId = (string) Str::uuid();
        }

        return $this->currentTraceId;
    }

    public function span(string $operationName, callable $callback, array $attributes = []): mixed
    {
        $previousAttributes = $this->currentSpanAttributes;

        $this->currentSpanAttributes = array_merge($attributes, [
            'operation' => $operationName,
            'trace_id' => $this->getTraceId(),
        ]);

        $this->enrichCurrentSpan();

        try {
            return $callback();
        } finally {
            $this->currentSpanAttributes = $previousAttributes;
        }
    }

    public function enrichCurrentSpan(): void
    {
        $tenant = $this->context->getOrNull();

        if ($tenant !== null) {
            $this->currentSpanAttributes['tenant_id'] = $tenant->tenantId;
            $this->currentSpanAttributes['tenant_uuid'] = $tenant->uuid;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getCurrentSpanAttributes(): array
    {
        return $this->currentSpanAttributes;
    }
}
