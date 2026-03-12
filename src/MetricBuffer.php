<?php

declare(strict_types=1);

namespace Monitaroo;

use Monitaroo\Transport\TransportInterface;

class MetricBuffer
{
    private TransportInterface $transport;
    private int $batchSize;
    private array $buffer = [];

    public function __construct(
        TransportInterface $transport,
        int $batchSize = 100
    ) {
        $this->transport = $transport;
        $this->batchSize = $batchSize;
    }

    /**
     * Add a metric to the buffer.
     *
     * @param string $type One of: counter, gauge, timer, histogram
     * @param string $name Metric name (e.g., "orders.completed")
     * @param float|int $value Metric value
     * @param array<string, string> $tags Key-value tags
     */
    public function add(string $type, string $name, float|int $value, array $tags = []): void
    {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');

        $this->buffer[] = [
            'name' => $this->sanitizeName($name),
            'type' => $this->normalizeType($type),
            'value' => (float) $value,
            'tags' => $this->sanitizeTags($tags),
            'timestamp' => $timestamp,
        ];

        // Auto-flush if batch size reached
        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Flush all buffered metrics to the transport.
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $metrics = $this->buffer;
        $this->buffer = [];

        try {
            $this->transport->sendMetrics($metrics);
        } catch (\Throwable $e) {
            // Silently fail - don't break the application
            error_log('Monitaroo: Failed to send metrics - ' . $e->getMessage());
        }
    }

    /**
     * Get the number of buffered metrics.
     */
    public function count(): int
    {
        return count($this->buffer);
    }

    /**
     * Sanitize metric name to valid format.
     * Allows: letters, numbers, dots, underscores, hyphens
     * Must start with a letter.
     */
    private function sanitizeName(string $name): string
    {
        // Replace invalid characters with underscores
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

        // Ensure starts with letter
        if (!preg_match('/^[a-zA-Z]/', $name)) {
            $name = 'm_' . $name;
        }

        // Limit length
        return substr($name, 0, 255);
    }

    /**
     * Normalize metric type.
     */
    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'counter', 'count', 'increment' => 'counter',
            'gauge', 'value' => 'gauge',
            'timer', 'timing', 'time' => 'timer',
            'histogram', 'distribution' => 'histogram',
            default => 'gauge',
        };
    }

    /**
     * Sanitize tags to ensure they're valid strings.
     *
     * @param array $tags
     * @return array<string, string>
     */
    private function sanitizeTags(array $tags): array
    {
        $sanitized = [];

        foreach ($tags as $key => $value) {
            $key = (string) $key;
            
            // Skip invalid keys
            if (empty($key) || strlen($key) > 255) {
                continue;
            }

            // Convert value to string
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (!is_string($value) && !is_numeric($value)) {
                continue; // Skip non-stringifiable values
            }

            $value = (string) $value;

            // Limit value length
            if (strlen($value) > 255) {
                $value = substr($value, 0, 255);
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
