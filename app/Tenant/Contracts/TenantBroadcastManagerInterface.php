<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Illuminate\Contracts\Broadcasting\Broadcaster;

interface TenantBroadcastManagerInterface
{
    /**
     * Get a broadcaster instance configured for the active tenant.
     */
    public function driver(?string $driver = null): Broadcaster;

    /**
     * Prefix a broadcast channel name with the current active tenant identifier.
     */
    public function formatChannelName(string $channel): string;

    /**
     * Broadcast an event on the tenant-scoped channels.
     *
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function event(array $channels, string $event, array $payload): void;
}
