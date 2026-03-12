<?php

declare(strict_types=1);

namespace Monitaroo;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * PSR-3 compatible logger that sends logs to Monitaroo.
 */
class Logger extends AbstractLogger
{
    private LogBuffer $buffer;

    public function __construct(LogBuffer $buffer)
    {
        $this->buffer = $buffer;
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->buffer->add(
            $this->normalizeLevel($level),
            (string) $message,
            $context
        );
    }

    /**
     * Normalize PSR-3 log level to Monitaroo level.
     */
    private function normalizeLevel(mixed $level): string
    {
        if (!is_string($level)) {
            return 'info';
        }

        return match (strtolower($level)) {
            LogLevel::DEBUG => 'debug',
            LogLevel::INFO => 'info',
            LogLevel::NOTICE => 'info',
            LogLevel::WARNING => 'warn',
            LogLevel::ERROR => 'error',
            LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY => 'fatal',
            default => 'info',
        };
    }

    /**
     * Flush buffered logs.
     */
    public function flush(): void
    {
        $this->buffer->flush();
    }
}
