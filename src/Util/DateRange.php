<?php

declare(strict_types=1);

namespace App\Util;

use DateTimeImmutable;
use Generator;
use InvalidArgumentException;

/**
 * @return Generator<int,DateTimeImmutable>
 * @throws InvalidArgumentException
 * Generates $days consecutive dates starting from $start (inclusive).
 */
function generateDateRange(DateTimeImmutable $start, int $days): Generator
{
    if ($days <= 0) {
        throw new InvalidArgumentException('Days must be positive.');
    }

    for ($i = 0; $i < $days; $i++) {
        yield $start->modify("+$i day");
    }
}
