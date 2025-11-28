<?php

declare(strict_types=1);

namespace App\Service;

interface MailSenderInterface
{
    /**
     * @param array<string,string> $headers
     */
    public function send(string $to, string $subject, string $body, array $headers = []): void;
}
