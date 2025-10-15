<?php

declare(strict_types=1);

namespace App\Contracts;

interface LoggerFactoryInterface
{
    public function createLogger(): LoggerInterface;
}
