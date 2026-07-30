<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

interface TenantTracingInterface
{
    /**
     * Start or retrieve the current distributed trace ID for the active execution cycle.
     */
    public function getTraceId(): string;

    /**
     * Start a tracing span for a specific operation within the active tenant context.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @param  array<string, mixed>  $attributes
     * @return TReturn
     */
    public function span(string $operationName, callable $callback, array $attributes = []): mixed;

    /**
     * Attach active tenant metadata (ID, UUID) to the current active span.
     */
    public function enrichCurrentSpan(): void;
}
