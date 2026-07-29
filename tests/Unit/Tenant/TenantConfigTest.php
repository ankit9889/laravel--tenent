<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Tenant\Context\TenantConfig;
use PHPUnit\Framework\TestCase;

final class TenantConfigTest extends TestCase
{
    public function test_it_creates_config_and_converts_to_array(): void
    {
        $config = new TenantConfig(
            tenantId: 10,
            uuid: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            companyName: 'Acme Enterprise',
            databaseName: 'tenant_10',
            databaseHost: '127.0.0.1',
            databasePort: 3306,
            databaseUsername: 'tenant_usr',
            databasePassword: 'secret_password',
            timezone: 'America/New_York',
            cachePrefix: 't_10:',
            storagePrefix: 'tenants/f47ac10b',
            isActive: true
        );

        $array = $config->toArray();

        $this->assertSame(10, $array['tenant_id']);
        $this->assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $array['uuid']);
        $this->assertSame('America/New_York', $array['timezone']);
        $this->assertTrue($array['is_active']);

        $hydrated = TenantConfig::fromArray($array);

        $this->assertEquals($config, $hydrated);
    }

    public function test_from_array_applies_defaults_when_optional_fields_missing(): void
    {
        $payload = [
            'tenant_id' => 5,
            'uuid' => 'a0000000-0000-0000-0000-000000000005',
            'company_name' => 'Minimal Corp',
            'database_name' => 'tenant_5',
            'database_host' => 'localhost',
            'database_port' => 3306,
            'database_username' => 'root',
            'database_password' => '',
        ];

        $config = TenantConfig::fromArray($payload);

        $this->assertSame('UTC', $config->timezone);
        $this->assertSame('tenant_5:', $config->cachePrefix);
        $this->assertSame('tenants/a0000000-0000-0000-0000-000000000005', $config->storagePrefix);
        $this->assertTrue($config->isActive);
    }
}
