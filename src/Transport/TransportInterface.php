<?php

declare(strict_types=1);

namespace Monitaroo\Transport;

interface TransportInterface
{
    /**
     * Send logs to Monitaroo.
     *
     * @param array $logs Array of log entries
     * @return void
     * @throws \RuntimeException If sending fails after retries
     */
    public function sendLogs(array $logs);

    /**
     * Send metrics to Monitaroo.
     *
     * @param array $metrics Array of metric entries
     * @return void
     * @throws \RuntimeException If sending fails after retries
     */
    public function sendMetrics(array $metrics);
}
