<?php

declare(strict_types=1);

namespace Monitaroo;

/**
 * Static facade for the Monitaroo client.
 *
 * This class provides static methods for easy access to logging and metrics
 * without needing to manage the client instance directly.
 *
 * Usage:
 *   Monitaroo::init(['apiKey' => 'mk_xxx']);
 *   Monitaroo::info('Hello world');
 *   Monitaroo::increment('orders.completed');
 */
class Monitaroo
{
    /**
     * Initialize the global Monitaroo client.
     *
     * @param array $options {
     *     @type string $apiKey API key (required)
     *     @type string $endpoint API endpoint
     *     @type string $service Service name
     *     @type string $environment Environment name
     *     @type string $host Host name
     *     @type int $batchSize Batch size before auto-flush
     *     @type bool $autoFlush Enable auto-flush on shutdown
     * }
     * @return Client
     */
    public static function init(array $options)
    {
        return Client::init($options);
    }

    /**
     * Get the global client instance.
     *
     * @return Client|null
     */
    public static function client()
    {
        return Client::getInstance();
    }

    /**
     * Get a PSR-3 compatible logger.
     *
     * @return Logger|null
     */
    public static function logger()
    {
        $client = Client::getInstance();
        return $client !== null ? $client->getLogger() : null;
    }

    // ========================================
    // LOGGING
    // ========================================

    /**
     * Log a trace message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function trace($message, array $context = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->trace($message, $context);
        }
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function debug($message, array $context = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->debug($message, $context);
        }
    }

    /**
     * Log an info message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function info($message, array $context = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->info($message, $context);
        }
    }

    /**
     * Log a warning message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function warn($message, array $context = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->warn($message, $context);
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function error($message, array $context = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->error($message, $context);
        }
    }

    /**
     * Log a fatal message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function fatal($message, array $context = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->fatal($message, $context);
        }
    }

    /**
     * Log a message with a specific level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function log($level, $message, array $context = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->log($level, $message, $context);
        }
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
    public static function increment($name, $value = 1, array $tags = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->increment($name, $value, $tags);
        }
    }

    /**
     * Set a gauge metric value.
     *
     * @param string $name
     * @param float $value
     * @param array $tags
     * @return void
     */
    public static function gauge($name, $value, array $tags = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->gauge($name, $value, $tags);
        }
    }

    /**
     * Record a timing metric (in milliseconds).
     *
     * @param string $name
     * @param float $milliseconds
     * @param array $tags
     * @return void
     */
    public static function timing($name, $milliseconds, array $tags = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->timing($name, $milliseconds, $tags);
        }
    }

    /**
     * Record a histogram value.
     *
     * @param string $name
     * @param float $value
     * @param array $tags
     * @return void
     */
    public static function histogram($name, $value, array $tags = [])
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->histogram($name, $value, $tags);
        }
    }

    /**
     * Start a timer and return a callable to stop it.
     *
     * Usage:
     *   $stop = Monitaroo::startTimer('db.query');
     *   // ... do something
     *   $elapsed = $stop(); // Records metric and returns elapsed ms
     *
     * @param string $name
     * @param array $tags
     * @return callable Returns elapsed time in ms when called, or null if no client
     */
    public static function startTimer($name, array $tags = [])
    {
        $client = Client::getInstance();
        
        if ($client === null) {
            return function () {
                return null;
            };
        }

        return $client->startTimer($name, $tags);
    }

    // ========================================
    // FLUSH
    // ========================================

    /**
     * Flush all buffered logs and metrics immediately.
     *
     * @return void
     */
    public static function flush()
    {
        $client = Client::getInstance();
        if ($client !== null) {
            $client->flush();
        }
    }
}
