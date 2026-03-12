<?php

declare(strict_types=1);

namespace Monitaroo;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * PSR-3 compatible logger that sends logs to Monitaroo.
 */
class Logger extends AbstractLogger
{
    /** @var LogBuffer */
    private $buffer;

    /**
     * @param LogBuffer $buffer
     */
    public function __construct(LogBuffer $buffer)
    {
        $this->buffer = $buffer;
    }

    /**
     * @inheritDoc
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->buffer->add(
            $this->normalizeLevel($level),
            (string) $message,
            $context
        );
    }

    /**
     * Normalize PSR-3 log level to Monitaroo level.
     *
     * @param mixed $level
     * @return string
     */
    private function normalizeLevel($level)
    {
        if (!is_string($level)) {
            return 'info';
        }

        $level = strtolower($level);

        switch ($level) {
            case LogLevel::DEBUG:
                return 'debug';
            case LogLevel::INFO:
                return 'info';
            case LogLevel::NOTICE:
                return 'info';
            case LogLevel::WARNING:
                return 'warn';
            case LogLevel::ERROR:
                return 'error';
            case LogLevel::CRITICAL:
            case LogLevel::ALERT:
            case LogLevel::EMERGENCY:
                return 'fatal';
            default:
                return 'info';
        }
    }

    /**
     * Flush buffered logs.
     *
     * @return void
     */
    public function flush()
    {
        $this->buffer->flush();
    }
}
