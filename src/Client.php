<?php

declare(strict_types=1);

namespace Monitaroo;

use Monitaroo\Transport\TransportInterface;
use Monitaroo\Transport\HttpTransport;

class Client
{
    /** @var Client|null */
    private static $instance = null;

    /** @var TransportInterface */
    private $transport;

    /** @var LogBuffer */
    private $logBuffer;

    /** @var MetricBuffer */
    private $metricBuffer;

    /** @var bool */
    private $autoFlush = true;

    /** @var bool */
    private $shutdownRegistered = false;

    /**
     * Create a new Monitaroo client.
     *
     * @param array $options {
     *     @type string $apiKey API key (required)
     *     @type string $endpoint API endpoint
     *     @type string $service Service name
     *     @type string $environment Environment name
     *     @type string $host Host name
     *     @type int $batchSize Batch size before auto-flush
     *     @type bool $autoFlush Enable auto-flush on shutdown
     *     @type TransportInterface $transport Custom transport
     * }
     */
    public function __construct(array $options)
    {
        if (empty($options['apiKey'])) {
            throw new \InvalidArgumentException('API key is required');
        }

        $this->transport = isset($options['transport']) 
            ? $options['transport'] 
            : new HttpTransport(
                $options['apiKey'],
                isset($options['endpoint']) ? $options['endpoint'] : 'https://api.monitaroo.com'
            );

        $defaultContext = [
            'service' => isset($options['service']) ? $options['service'] : '',
            'environment' => isset($options['environment']) ? $options['environment'] : '',
            'host' => isset($options['host']) ? $options['host'] : (gethostname() ?: ''),
        ];

        $batchSize = isset($options['batchSize']) ? $options['batchSize'] : 100;
        $this->autoFlush = isset($options['autoFlush']) ? $options['autoFlush'] : true;

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
    public static function init(array $options)
    {
        self::$instance = new self($options);
        return self::$instance;
    }

    /**
     * Get the global client instance.
     *
     * @return Client|null
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * Get a PSR-3 compatible logger.
     *
     * @return Logger
     */
    public function getLogger()
    {
        return new Logger($this->logBuffer);
    }

    // ========================================
    // LOGGING
    // ========================================

    /**
     * Log a message with a specific level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->logBuffer->add($level, $message, $context);
    }

    /**
     * Log a trace message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function trace($message, array $context = [])
    {
        $this->log('trace', $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warn($message, array $context = [])
    {
        $this->log('warn', $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log a fatal message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function fatal($message, array $context = [])
    {
        $this->log('fatal', $message, $context);
    }

    // ========================================
    // METRICS
    // ========================================

    /**
     * Increment a counter metric.
     *
     * @param string $name
     * @param int $value
     * @param array $tags
     * @return void
     */
    public function increment($name, $value = 1, array $tags = [])
    {
        $this->metricBuffer->add('counter', $name, $value, $tags);
    }

    /**
     * Set a gauge metric value.
     *
     * @param string $name
     * @param float $value
     * @param array $tags
     * @return void
     */
    public function gauge($name, $value, array $tags = [])
    {
        $this->metricBuffer->add('gauge', $name, $value, $tags);
    }

    /**
     * Record a timing metric (in milliseconds).
     *
     * @param string $name
     * @param float $milliseconds
     * @param array $tags
     * @return void
     */
    public function timing($name, $milliseconds, array $tags = [])
    {
        $this->metricBuffer->add('timer', $name, $milliseconds, $tags);
    }

    /**
     * Record a histogram value.
     *
     * @param string $name
     * @param float $value
     * @param array $tags
     * @return void
     */
    public function histogram($name, $value, array $tags = [])
    {
        $this->metricBuffer->add('histogram', $name, $value, $tags);
    }

    /**
     * Start a timer and return a callable to stop it.
     *
     * @param string $name
     * @param array $tags
     * @return callable Returns elapsed time in ms when called
     */
    public function startTimer($name, array $tags = [])
    {
        $start = microtime(true);
        $client = $this;

        return function () use ($name, $tags, $start, $client) {
            $elapsed = (microtime(true) - $start) * 1000; // Convert to ms
            $client->timing($name, $elapsed, $tags);
            return $elapsed;
        };
    }

    // ========================================
    // FLUSH
    // ========================================

    /**
     * Flush all buffered logs and metrics.
     *
     * @return void
     */
    public function flush()
    {
        $this->logBuffer->flush();
        $this->metricBuffer->flush();
    }

    /**
     * Register shutdown function to auto-flush.
     *
     * @return void
     */
    private function registerShutdown()
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $client = $this;
        register_shutdown_function(function () use ($client) {
            // Try to finish request first (user gets response faster)
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $client->flush();
        });

        $this->shutdownRegistered = true;
    }

    // ========================================
    // STATIC HELPERS
    // ========================================

    /**
     * Static helper to log via global instance.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function logStatic($level, $message, array $context = [])
    {
        if (self::$instance !== null) {
            self::$instance->log($level, $message, $context);
        }
    }

    /**
     * Static helper to increment via global instance.
     *
     * @param string $name
     * @param int $value
     * @param array $tags
     * @return void
     */
    public static function incrementStatic($name, $value = 1, array $tags = [])
    {
        if (self::$instance !== null) {
            self::$instance->increment($name, $value, $tags);
        }
    }

    /**
     * Static helper to set gauge via global instance.
     *
     * @param string $name
     * @param float $value
     * @param array $tags
     * @return void
     */
    public static function gaugeStatic($name, $value, array $tags = [])
    {
        if (self::$instance !== null) {
            self::$instance->gauge($name, $value, $tags);
        }
    }

    /**
     * Static helper to record timing via global instance.
     *
     * @param string $name
     * @param float $milliseconds
     * @param array $tags
     * @return void
     */
    public static function timingStatic($name, $milliseconds, array $tags = [])
    {
        if (self::$instance !== null) {
            self::$instance->timing($name, $milliseconds, $tags);
        }
    }

    /**
     * Static helper to flush via global instance.
     *
     * @return void
     */
    public static function flushStatic()
    {
        if (self::$instance !== null) {
            self::$instance->flush();
        }
    }
}
