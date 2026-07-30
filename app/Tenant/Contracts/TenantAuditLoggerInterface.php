<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

interface TenantAuditLoggerInterface
{
    /**
     * Record an audit log entry for the current active tenant.
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, string $description, array $properties = [], ?int $userId = null): void;

    /**
     * Log a security-sensitive event for the current active tenant.
     *
     * @param  array<string, mixed>  $properties
     */
    public function security(string $event, string $description, array $properties = [], ?int $userId = null): void;
}
