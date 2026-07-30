<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantAuditLoggerInterface;
use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantDatabaseManagerInterface;
use Illuminate\Database\DatabaseManager;

final class TenantAuditLogger implements TenantAuditLoggerInterface
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TenantContextInterface $context,
        private readonly TenantDatabaseManagerInterface $databaseManager
    ) {}

    public function log(string $event, string $description, array $properties = [], ?int $userId = null): void
    {
        $this->record('info', $event, $description, $properties, $userId);
    }

    public function security(string $event, string $description, array $properties = [], ?int $userId = null): void
    {
        $this->record('security', $event, $description, $properties, $userId);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function record(string $type, string $event, string $description, array $properties, ?int $userId): void
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return;
        }

        $connection = $this->databaseManager->isTenantConnectionActive()
            ? $this->db->connection('tenant')
            : $this->db->connection('mysql');

        $table = $this->databaseManager->isTenantConnectionActive()
            ? 'tenant_audit_logs'
            : 'client_audit_logs';

        $payload = [
            'tenant_id' => $tenant->tenantId,
            'user_id' => $userId,
            'event_type' => $type,
            'event_name' => $event,
            'description' => $description,
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $connection->table($table)->insert($payload);
        } catch (\Throwable) {
            // Ensure audit logger failure never halts core CRM business transactions
        }
    }
}
