<?php

declare(strict_types=1);

namespace App\Exception;

use Exception;

final class ApiException extends Exception
{
    private int $statusCode;

    public function __construct(string $message = '', int $statusCode = 500, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
