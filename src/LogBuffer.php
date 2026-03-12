<?php

declare(strict_types=1);

namespace Monitaroo;

use Monitaroo\Transport\TransportInterface;

class LogBuffer
{
    private TransportInterface $transport;
    private array $defaultContext;
    private int $batchSize;
    private array $buffer = [];

    public function __construct(
        TransportInterface $transport,
        array $defaultContext = [],
        int $batchSize = 100
    ) {
        $this->transport = $transport;
        $this->defaultContext = $defaultContext;
        $this->batchSize = $batchSize;
    }

    /**
     * Add a log entry to the buffer.
     */
    public function add(string $level, string $message, array $context = []): void
    {
        $timestamp = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z');

        // Separate tags from attributes
        $tags = $context['tags'] ?? [];
        unset($context['tags']);

        // Build log entry
        $log = [
            'timestamp' => $timestamp,
            'level' => $this->normalizeLevel($level),
            'message' => $this->interpolateMessage($message, $context),
            'service' => $context['service'] ?? $this->defaultContext['service'] ?? '',
            'environment' => $context['environment'] ?? $this->defaultContext['environment'] ?? '',
            'host' => $context['host'] ?? $this->defaultContext['host'] ?? '',
            'tags' => $tags,
            'attributes' => $this->filterAttributes($context),
        ];

        $this->buffer[] = $log;

        // Auto-flush if batch size reached
        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Flush all buffered logs to the transport.
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $logs = $this->buffer;
        $this->buffer = [];

        try {
            $this->transport->sendLogs($logs);
        } catch (\Throwable $e) {
            // Silently fail - don't break the application
            // Optionally could log to error_log
            error_log('Monitaroo: Failed to send logs - ' . $e->getMessage());
        }
    }

    /**
     * Get the number of buffered logs.
     */
    public function count(): int
    {
        return count($this->buffer);
    }

    /**
     * Normalize log level to valid values.
     */
    private function normalizeLevel(string $level): string
    {
        $level = strtolower($level);

        return match ($level) {
            'trace' => 'trace',
            'debug' => 'debug',
            'info', 'notice' => 'info',
            'warn', 'warning' => 'warn',
            'error' => 'error',
            'fatal', 'critical', 'emergency', 'alert' => 'fatal',
            default => 'info',
        };
    }

    /**
     * Interpolate placeholders in message with context values.
     * Follows PSR-3 placeholder format: {key}
     */
    private function interpolateMessage(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * Filter context to only include serializable attributes.
     */
    private function filterAttributes(array $context): array
    {
        // Remove reserved keys
        $reserved = ['service', 'environment', 'host', 'tags', 'exception'];
        $attributes = array_diff_key($context, array_flip($reserved));

        // Handle exception specially
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $attributes['exception'] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 10), // Limit trace depth
            ];
        }

        return $this->makeSerializable($attributes);
    }

    /**
     * Ensure all values are JSON-serializable.
     */
    private function makeSerializable(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->makeSerializable($value);
            } elseif (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $result[$key] = (string) $value;
                } elseif ($value instanceof \JsonSerializable) {
                    $result[$key] = $value->jsonSerialize();
                } elseif ($value instanceof \DateTimeInterface) {
                    $result[$key] = $value->format('c');
                } else {
                    $result[$key] = '[object ' . get_class($value) . ']';
                }
            } elseif (is_resource($value)) {
                $result[$key] = '[resource]';
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
