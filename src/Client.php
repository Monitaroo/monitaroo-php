<?php

declare(strict_types=1);

namespace Monitaroo;

use Monitaroo\Transport\TransportInterface;
use Monitaroo\Transport\HttpTransport;

class Client
{
    private static ?Client $instance = null;

    private TransportInterface $transport;
    private LogBuffer $logBuffer;
    private MetricBuffer $metricBuffer;
    private bool $autoFlush = true;
    private bool $shutdownRegistered = false;

    /**
     * Create a new Monitaroo client.
     *
     * @param array{
     *     apiKey: string,
     *     endpoint?: string,
     *     service?: string,
     *     environment?: string,
     *     host?: string,
     *     batchSize?: int,
     *     autoFlush?: bool,
     *     transport?: TransportInterface
     * } $options
     */
    public function __construct(array $options)
    {
        if (empty($options['apiKey'])) {
            throw new \InvalidArgumentException('API key is required');
        }

        $this->transport = $options['transport'] ?? new HttpTransport(
            $options['apiKey'],
            $options['endpoint'] ?? 'https://api.monitaroo.com'
        );

        $defaultContext = [
            'service' => $options['service'] ?? '',
            'environment' => $options['environment'] ?? '',
            'host' => $options['host'] ?? gethostname() ?: '',
        ];

        $batchSize = $options['batchSize'] ?? 100;
        $this->autoFlush = $options['autoFlush'] ?? true;

        $this->logBuffer = new LogBuffer($this->transport, $defaultContext, $batchSize);
        $this->metricBuffer = new MetricBuffer($this->transport, $batchSize);

        if ($this->autoFlush) {
            $this->registerShutdown();
        }
    }

    /**
     * Initialize the global Monitaroo client.
     *
     * @param array $options
     * @return Client
     */
    public static function init(array $options): Client
    {
        self::$instance = new self($options);
        return self::$instance;
    }

    /**
     * Get the global client instance.
     *
     * @return Client|null
     */
    public static function getInstance(): ?Client
    {
        return self::$instance;
    }

    /**
     * Get a PSR-3 compatible logger.
     *
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return new Logger($this->logBuffer);
    }

    // ========================================
    // LOGGING
    // ========================================

    /**
     * Log a message with a specific level.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logBuffer->add($level, $message, $context);
    }

    /**
     * Log a trace message.
     */
    public function trace(string $message, array $context = []): void
    {
        $this->log('trace', $message, $context);
    }

    /**
     * Log a debug message.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log an info message.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log a warning message.
     */
    public function warn(string $message, array $context = []): void
    {
        $this->log('warn', $message, $context);
    }

    /**
     * Log an error message.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log a fatal message.
     */
    public function fatal(string $message, array $context = []): void
    {
        $this->log('fatal', $message, $context);
    }

    // ========================================
    // METRICS
    // ========================================

    /**
     * Increment a counter metric.
     */
    public function increment(string $name, int $value = 1, array $tags = []): void
    {
        $this->metricBuffer->add('counter', $name, $value, $tags);
    }

    /**
     * Set a gauge metric value.
     */
    public function gauge(string $name, float $value, array $tags = []): void
    {
        $this->metricBuffer->add('gauge', $name, $value, $tags);
    }

    /**
     * Record a timing metric (in milliseconds).
     */
    public function timing(string $name, float $milliseconds, array $tags = []): void
    {
        $this->metricBuffer->add('timer', $name, $milliseconds, $tags);
    }

    /**
     * Record a histogram value.
     */
    public function histogram(string $name, float $value, array $tags = []): void
    {
        $this->metricBuffer->add('histogram', $name, $value, $tags);
    }

    /**
     * Start a timer and return a callable to stop it.
     *
     * @return callable(): float Returns elapsed time in ms when called
     */
    public function startTimer(string $name, array $tags = []): callable
    {
        $start = hrtime(true);

        return function () use ($name, $tags, $start): float {
            $elapsed = (hrtime(true) - $start) / 1_000_000; // Convert to ms
            $this->timing($name, $elapsed, $tags);
            return $elapsed;
        };
    }

    // ========================================
    // FLUSH
    // ========================================

    /**
     * Flush all buffered logs and metrics.
     */
    public function flush(): void
    {
        $this->logBuffer->flush();
        $this->metricBuffer->flush();
    }

    /**
     * Register shutdown function to auto-flush.
     */
    private function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        register_shutdown_function(function () {
            // Try to finish request first (user gets response faster)
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $this->flush();
        });

        $this->shutdownRegistered = true;
    }

    // ========================================
    // STATIC HELPERS
    // ========================================

    /**
     * Static helper to log via global instance.
     */
    public static function logStatic(string $level, string $message, array $context = []): void
    {
        self::$instance?->log($level, $message, $context);
    }

    /**
     * Static helper to increment via global instance.
     */
    public static function incrementStatic(string $name, int $value = 1, array $tags = []): void
    {
        self::$instance?->increment($name, $value, $tags);
    }

    /**
     * Static helper to set gauge via global instance.
     */
    public static function gaugeStatic(string $name, float $value, array $tags = []): void
    {
        self::$instance?->gauge($name, $value, $tags);
    }

    /**
     * Static helper to record timing via global instance.
     */
    public static function timingStatic(string $name, float $milliseconds, array $tags = []): void
    {
        self::$instance?->timing($name, $milliseconds, $tags);
    }

    /**
     * Static helper to flush via global instance.
     */
    public static function flushStatic(): void
    {
        self::$instance?->flush();
    }
}
