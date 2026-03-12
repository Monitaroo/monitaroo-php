<?php

declare(strict_types=1);

namespace Monitaroo;

use Monitaroo\Transport\TransportInterface;

class LogBuffer
{
    /** @var TransportInterface */
    private $transport;

    /** @var array */
    private $defaultContext;

    /** @var int */
    private $batchSize;

    /** @var array */
    private $buffer = [];

    /**
     * @param TransportInterface $transport
     * @param array $defaultContext
     * @param int $batchSize
     */
    public function __construct(
        TransportInterface $transport,
        array $defaultContext = [],
        $batchSize = 100
    ) {
        $this->transport = $transport;
        $this->defaultContext = $defaultContext;
        $this->batchSize = $batchSize;
    }

    /**
     * Add a log entry to the buffer.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function add($level, $message, array $context = [])
    {
        $timestamp = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z');

        // Separate tags from attributes
        $tags = isset($context['tags']) ? $context['tags'] : [];
        unset($context['tags']);

        // Build log entry
        $log = [
            'timestamp' => $timestamp,
            'level' => $this->normalizeLevel($level),
            'message' => $this->interpolateMessage($message, $context),
            'service' => isset($context['service']) ? $context['service'] : (isset($this->defaultContext['service']) ? $this->defaultContext['service'] : ''),
            'environment' => isset($context['environment']) ? $context['environment'] : (isset($this->defaultContext['environment']) ? $this->defaultContext['environment'] : ''),
            'host' => isset($context['host']) ? $context['host'] : (isset($this->defaultContext['host']) ? $this->defaultContext['host'] : ''),
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
     *
     * @return void
     */
    public function flush()
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
            error_log('Monitaroo: Failed to send logs - ' . $e->getMessage());
        }
    }

    /**
     * Get the number of buffered logs.
     *
     * @return int
     */
    public function count()
    {
        return count($this->buffer);
    }

    /**
     * Normalize log level to valid values.
     *
     * @param string $level
     * @return string
     */
    private function normalizeLevel($level)
    {
        $level = strtolower($level);

        switch ($level) {
            case 'trace':
                return 'trace';
            case 'debug':
                return 'debug';
            case 'info':
            case 'notice':
                return 'info';
            case 'warn':
            case 'warning':
                return 'warn';
            case 'error':
                return 'error';
            case 'fatal':
            case 'critical':
            case 'emergency':
            case 'alert':
                return 'fatal';
            default:
                return 'info';
        }
    }

    /**
     * Interpolate placeholders in message with context values.
     * Follows PSR-3 placeholder format: {key}
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    private function interpolateMessage($message, array $context)
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
     *
     * @param array $context
     * @return array
     */
    private function filterAttributes(array $context)
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
     *
     * @param array $data
     * @return array
     */
    private function makeSerializable(array $data)
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
