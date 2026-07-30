<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Context\TenantConfig;
use App\Tenant\Contracts\TenantConfigManagerInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class TenantConfigManager implements TenantConfigManagerInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $originalConfigs = [];

    public function __construct(
        private readonly ConfigRepository $config
    ) {}

    public function apply(TenantConfig $tenant): void
    {
        $this->originalConfigs = [
            'app.timezone' => $this->config->get('app.timezone'),
            'cache.prefix' => $this->config->get('cache.prefix'),
            'filesystems.default' => $this->config->get('filesystems.default'),
        ];

        $this->config->set('app.timezone', $tenant->timezone);
        $this->config->set('cache.prefix', $tenant->cachePrefix);

        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set($tenant->timezone);
        }
    }

    public function revert(): void
    {
        foreach ($this->originalConfigs as $key => $value) {
            $this->config->set($key, $value);
        }

        $originalTimezone = $this->originalConfigs['app.timezone'] ?? 'UTC';
        if (is_string($originalTimezone) && function_exists('date_default_timezone_set')) {
            date_default_timezone_set($originalTimezone);
        }

        $this->originalConfigs = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->config->set($key, $value);
    }
}
