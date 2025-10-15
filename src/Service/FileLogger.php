<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\LoggerInterface;
use DateTimeImmutable;
use RuntimeException;

final class FileLogger implements LoggerInterface
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
        $directory = dirname($logFile);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create log directory: %s', $directory));
        }
        if (!is_writable($directory)) {
            throw new RuntimeException(sprintf('Log directory is not writable: %s', $directory));
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $contextSuffix = '';
        if ($context !== []) {
            $encoded = json_encode($this->normalizeContext($context), JSON_UNESCAPED_UNICODE);
            $contextSuffix = $encoded === false ? ' ' . '[context_encoding_error]' : ' ' . $encoded;
        }
        $entry = sprintf('%s [%s] %s%s%s', $timestamp, strtoupper($level), $message, $contextSuffix, PHP_EOL);
        file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            $context[$key] = $this->stringify($value);
        }

        return $context;
    }

    private function stringify(mixed $value): mixed
    {
        if ($value instanceof \Throwable) {
            return $value::class . ': ' . $value->getMessage();
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : $value::class;
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->stringify($v), $value);
        }

        return $value;
    }
}
