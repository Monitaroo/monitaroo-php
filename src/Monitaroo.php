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
     * @param array{
     *     apiKey: string,
     *     endpoint?: string,
     *     service?: string,
     *     environment?: string,
     *     host?: string,
     *     batchSize?: int,
     *     autoFlush?: bool
     * } $options
     * @return Client
     */
    public static function init(array $options): Client
    {
        return Client::init($options);
    }

    /**
     * Get the global client instance.
     */
    public static function client(): ?Client
    {
        return Client::getInstance();
    }

    /**
     * Get a PSR-3 compatible logger.
     */
    public static function logger(): ?Logger
    {
        return Client::getInstance()?->getLogger();
    }

    // ========================================
    // LOGGING
    // ========================================

    /**
     * Log a trace message.
     */
    public static function trace(string $message, array $context = []): void
    {
        Client::getInstance()?->trace($message, $context);
    }

    /**
     * Log a debug message.
     */
    public static function debug(string $message, array $context = []): void
    {
        Client::getInstance()?->debug($message, $context);
    }

    /**
     * Log an info message.
     */
    public static function info(string $message, array $context = []): void
    {
        Client::getInstance()?->info($message, $context);
    }

    /**
     * Log a warning message.
     */
    public static function warn(string $message, array $context = []): void
    {
        Client::getInstance()?->warn($message, $context);
    }

    /**
     * Log an error message.
     */
    public static function error(string $message, array $context = []): void
    {
        Client::getInstance()?->error($message, $context);
    }

    /**
     * Log a fatal message.
     */
    public static function fatal(string $message, array $context = []): void
    {
        Client::getInstance()?->fatal($message, $context);
    }

    /**
     * Log a message with a specific level.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        Client::getInstance()?->log($level, $message, $context);
    }

    // ========================================
    // METRICS
    // ========================================

    /**
     * Increment a counter metric.
     */
    public static function increment(string $name, int $value = 1, array $tags = []): void
    {
        Client::getInstance()?->increment($name, $value, $tags);
    }

    /**
     * Set a gauge metric value.
     */
    public static function gauge(string $name, float $value, array $tags = []): void
    {
        Client::getInstance()?->gauge($name, $value, $tags);
    }

    /**
     * Record a timing metric (in milliseconds).
     */
    public static function timing(string $name, float $milliseconds, array $tags = []): void
    {
        Client::getInstance()?->timing($name, $milliseconds, $tags);
    }

    /**
     * Record a histogram value.
     */
    public static function histogram(string $name, float $value, array $tags = []): void
    {
        Client::getInstance()?->histogram($name, $value, $tags);
    }

    /**
     * Start a timer and return a callable to stop it.
     *
     * Usage:
     *   $stop = Monitaroo::startTimer('db.query');
     *   // ... do something
     *   $elapsed = $stop(); // Records metric and returns elapsed ms
     *
     * @return callable(): float|null Returns elapsed time in ms when called
     */
    public static function startTimer(string $name, array $tags = []): callable
    {
        $client = Client::getInstance();
        
        if ($client === null) {
            return fn() => null;
        }

        return $client->startTimer($name, $tags);
    }

    // ========================================
    // FLUSH
    // ========================================

    /**
     * Flush all buffered logs and metrics immediately.
     */
    public static function flush(): void
    {
        Client::getInstance()?->flush();
    }
}
