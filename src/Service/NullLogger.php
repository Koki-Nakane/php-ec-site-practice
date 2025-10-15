<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\LoggerInterface;

final class NullLogger implements LoggerInterface
{
    public function log(string $level, string $message, array $context = []): void
    {
        // intentionally no-op
    }
}
