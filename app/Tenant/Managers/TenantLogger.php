<?php

declare(strict_types=1);

namespace App\Tenant\Managers;

use App\Tenant\Contracts\TenantContextInterface;
use App\Tenant\Contracts\TenantLoggerInterface;
use Illuminate\Log\LogManager;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use Stringable;

final class TenantLogger implements TenantLoggerInterface
{
    public function __construct(
        private readonly LogManager $logManager,
        private readonly TenantContextInterface $context
    ) {}

    public function getProcessor(): callable
    {
        return function (LogRecord $record): LogRecord {
            $tenant = $this->context->getOrNull();

            if ($tenant === null) {
                return $record;
            }

            $extra = $record->extra;
            $extra['tenant_id'] = $tenant->tenantId;
            $extra['tenant_uuid'] = $tenant->uuid;
            $extra['tenant_db'] = $tenant->databaseName;

            return $record->with(extra: $extra);
        };
    }

    public function channel(?string $channel = null): LoggerInterface
    {
        return $this->logManager->channel($channel);
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->logManager->emergency($message, $this->enrichContext($context));
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->logManager->alert($message, $this->enrichContext($context));
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->logManager->critical($message, $this->enrichContext($context));
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->logManager->error($message, $this->enrichContext($context));
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->logManager->warning($message, $this->enrichContext($context));
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->logManager->notice($message, $this->enrichContext($context));
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->logManager->info($message, $this->enrichContext($context));
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->logManager->debug($message, $this->enrichContext($context));
    }

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->logManager->log($level, $message, $this->enrichContext($context));
    }

    /**
     * @param  array<int|string, mixed>  $context
     * @return array<int|string, mixed>
     */
    private function enrichContext(array $context): array
    {
        $tenant = $this->context->getOrNull();

        if ($tenant !== null) {
            $context['tenant_id'] = $tenant->tenantId;
            $context['tenant_uuid'] = $tenant->uuid;
        }

        return $context;
    }
}
