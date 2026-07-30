<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantNotificationManagerInterface;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class TenantNotificationManager implements TenantNotificationManagerInterface
{
    public function send(mixed $notifiables, Notification $notification): void
    {
        NotificationFacade::send($notifiables, $notification);
    }

    public function sendNow(mixed $notifiables, Notification $notification): void
    {
        NotificationFacade::sendNow($notifiables, $notification);
    }
}
