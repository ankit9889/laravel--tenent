<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Illuminate\Notifications\Notification;

interface TenantNotificationManagerInterface
{
    /**
     * Send a notification to the given notifiable entities within the current active tenant context.
     */
    public function send(mixed $notifiables, Notification $notification): void;

    /**
     * Send a notification immediately without queuing.
     */
    public function sendNow(mixed $notifiables, Notification $notification): void;
}
