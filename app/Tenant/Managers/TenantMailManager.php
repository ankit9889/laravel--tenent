<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantMailManagerInterface;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Mail\MailQueue;
use Illuminate\Mail\MailManager;

final class TenantMailManager implements TenantMailManagerInterface
{
    public function __construct(
        private readonly MailManager $mailManager,
        private readonly TenantContextInterface $context
    ) {}

    public function mailer(?string $name = null): Mailer
    {
        return $this->mailManager->mailer($name);
    }

    public function send(Mailable $mailable): void
    {
        $tenant = $this->context->getOrNull();

        if ($tenant !== null && $mailable instanceof \Illuminate\Mail\Mailable) {
            $mailable->with([
                'tenant_id' => $tenant->tenantId,
                'tenant_uuid' => $tenant->uuid,
                'company_name' => $tenant->companyName,
            ]);
        }

        $this->mailer()->send($mailable);
    }

    public function queue(Mailable $mailable): mixed
    {
        $tenant = $this->context->getOrNull();

        if ($tenant !== null && $mailable instanceof \Illuminate\Mail\Mailable) {
            $mailable->with([
                'tenant_id' => $tenant->tenantId,
                'tenant_uuid' => $tenant->uuid,
                'company_name' => $tenant->companyName,
            ]);
        }

        $mailer = $this->mailer();

        if ($mailer instanceof MailQueue) {
            return $mailer->queue($mailable);
        }

        throw new \RuntimeException('Current mailer driver does not support queueing mail.');
    }
}
