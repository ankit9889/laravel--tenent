<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantRedisPrefixManagerInterface;
use Illuminate\Redis\RedisManager;

final class TenantRedisPrefixManager implements TenantRedisPrefixManagerInterface
{
    public function __construct(
        private readonly RedisManager $redis,
        private readonly TenantContextInterface $context
    ) {}

    public function getPrefix(): string
    {
        $tenant = $this->context->getOrNull();

        return $tenant !== null ? $tenant->cachePrefix : 'global:';
    }

    public function key(string $name): string
    {
        return $this->getPrefix().$name;
    }

    public function applyPrefixToConnection(string $connection = 'default'): void
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return;
        }

        $client = $this->redis->connection($connection);

        if (method_exists($client, 'setOption')) {
            $client->setOption(\Redis::OPT_PREFIX, $tenant->cachePrefix);
        }
    }
}
