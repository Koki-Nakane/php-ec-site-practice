<?php

declare(strict_types=1);

namespace App\Factory;

use App\Contracts\ContainerInterface;
use App\Contracts\LoggerFactoryInterface;
use App\Contracts\LoggerInterface;
use App\Service\DatabaseLogger;
use App\Service\FileLogger;
use InvalidArgumentException;

final class ConfigurableLoggerFactory implements LoggerFactoryInterface
{
    private ?LoggerInterface $cachedLogger = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config,
        private ContainerInterface $container
    ) {
    }

    public function createLogger(): LoggerInterface
    {
        if ($this->cachedLogger !== null) {
            return $this->cachedLogger;
        }

        $driver = (string)($this->config['driver'] ?? 'file');

        $logger = match ($driver) {
            'file' => $this->createFileLogger(),
            'database' => $this->createDatabaseLogger(),
            default => throw new InvalidArgumentException(sprintf('Unsupported logger driver: %s', $driver)),
        };

        return $this->cachedLogger = $logger;
    }

    private function createFileLogger(): LoggerInterface
    {
        $fileConfig = $this->config['file'] ?? [];
        if (!is_array($fileConfig)) {
            throw new InvalidArgumentException('File logger configuration must be an array.');
        }
        $path = $fileConfig['path'] ?? null;
        if (!is_string($path) || $path === '') {
            throw new InvalidArgumentException('File logger requires a non-empty "path" setting.');
        }

        return new FileLogger($path);
    }

    private function createDatabaseLogger(): LoggerInterface
    {
        $dbConfig = $this->config['database'] ?? [];
        if (!is_array($dbConfig)) {
            throw new InvalidArgumentException('Database logger configuration must be an array.');
        }
        $table = $dbConfig['table'] ?? 'logs';
        if (!is_string($table) || $table === '') {
            throw new InvalidArgumentException('Database logger requires a non-empty "table" setting.');
        }

        $pdo = $this->container->get(\PDO::class);

        return new DatabaseLogger($pdo, $table);
    }
}
