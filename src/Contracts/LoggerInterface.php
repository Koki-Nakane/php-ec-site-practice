<?php

declare(strict_types=1);

namespace App\Contracts;

interface LoggerInterface
{
    /**
     * Persist a log entry.
     *
     * @param string $level   Log severity (e.g. info, warning, error)
     * @param string $message Human readable log message
     * @param array<string, mixed> $context Structured metadata for the log entry
     */
    public function log(string $level, string $message, array $context = []): void;
}
