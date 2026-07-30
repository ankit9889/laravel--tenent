<?php

declare(strict_types=1);

namespace App\Tenant\Contracts;

use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

interface TenantLoggerInterface extends LoggerInterface
{
    /**
     * Get the Monolog processor callback that enriches log records with active tenant metadata.
     *
     * @return callable(LogRecord): LogRecord
     */
    public function getProcessor(): callable;

    /**
     * Get a logger channel scoped to the active tenant.
     */
    public function channel(?string $channel = null): LoggerInterface;
}
