<?php

declare(strict_types=1);

namespace App\Service\Exception;

use DateTimeImmutable;
use RuntimeException;

final class NoOrdersForExportException extends RuntimeException
{
    private DateTimeImmutable $month;

    public function __construct(DateTimeImmutable $month)
    {
        parent::__construct('No orders found for export.');
        $this->month = $month;
    }

    public function getMonth(): DateTimeImmutable
    {
        return $this->month;
    }
}
