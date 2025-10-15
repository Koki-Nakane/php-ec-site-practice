<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\LoggerInterface;
use DateTimeImmutable;
use PDOException;
use RuntimeException;

final class DatabaseLogger implements LoggerInterface
{
    public function __construct(
        private \PDO $pdo,
        private string $table = 'logs'
    ) {
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $contextJson = json_encode($this->normalizeContext($context), JSON_UNESCAPED_UNICODE);
        if ($contextJson === false) {
            $contextJson = null;
        }

        $sql = sprintf(
            'INSERT INTO %s (level, message, context, created_at) VALUES (:level, :message, :context, :created_at)',
            $this->table
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':level' => $level,
                ':message' => $message,
                ':context' => $contextJson,
                ':created_at' => $timestamp,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to persist log entry to database', previous: $exception);
        }
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
