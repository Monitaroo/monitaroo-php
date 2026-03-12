<?php

declare(strict_types=1);

namespace Monitaroo\Transport;

interface TransportInterface
{
    /**
     * Send logs to Monitaroo.
     *
     * @param array $logs Array of log entries
     * @throws \RuntimeException If sending fails after retries
     */
    public function sendLogs(array $logs): void;

    /**
     * Send metrics to Monitaroo.
     *
     * @param array $metrics Array of metric entries
     * @throws \RuntimeException If sending fails after retries
     */
    public function sendMetrics(array $metrics): void;
}
