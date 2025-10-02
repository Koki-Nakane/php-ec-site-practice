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

        // Write logs under var/log/app.log (create directory if missing)
        $logDir = __DIR__ . '/../../var/log';
        if (!is_dir($logDir)) {
            // 0775 is reasonable for dev; umask may further restrict
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/app.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
