<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Mail\Mailer;

interface TenantMailManagerInterface
{
    /**
     * Get a mailer instance configured for the active tenant.
     */
    public function mailer(?string $name = null): Mailer;

    /**
     * Send a mailable using the active tenant's mailer configuration.
     */
    public function send(Mailable $mailable): void;

    /**
     * Queue a mailable on the tenant-scoped mail queue.
     */
    public function queue(Mailable $mailable): mixed;
}
