<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantBroadcastManagerInterface;
use App\Tenant\Contracts\TenantContextInterface;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;

final class TenantBroadcastManager implements TenantBroadcastManagerInterface
{
    public function __construct(
        private readonly BroadcastManager $broadcastManager,
        private readonly TenantContextInterface $context
    ) {}

    public function driver(?string $driver = null): Broadcaster
    {
        /** @var Broadcaster $broadcaster */
        $broadcaster = $this->broadcastManager->driver($driver);

        return $broadcaster;
    }

    public function formatChannelName(string $channel): string
    {
        $tenant = $this->context->getOrNull();

        if ($tenant === null) {
            return $channel;
        }

        return "tenant.{$tenant->uuid}.{$channel}";
    }

    public function event(array $channels, string $event, array $payload): void
    {
        $tenant = $this->context->getOrNull();

        if ($tenant !== null) {
            $payload['tenant_uuid'] = $tenant->uuid;
        }

        $formattedChannels = array_map(fn (string $channel): string => $this->formatChannelName($channel), $channels);

        $this->driver()->broadcast($formattedChannels, $event, $payload);
    }
}
