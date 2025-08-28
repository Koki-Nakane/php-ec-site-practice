<?php

declare(strict_types=1);

namespace App\Traits;

use DateTime;

trait Logger

{
    public function log(string $message): void
    {
        $date = new DateTime();
        $logEntry = $date->format('Y-m-d H:i:s') . ': ' . $message . "\n";
        file_put_contents('app.log', $logEntry, FILE_APPEND);
    }
}