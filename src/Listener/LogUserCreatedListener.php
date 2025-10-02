<?php

declare(strict_types=1);

namespace App\Listener;

use App\Infrastructure\Event;

final class LogUserCreatedListener
{
    public function __invoke(Event $event): void
    {
        $payload = $event->getPayload();
        $userEmail = $payload['email'] ?? 'unknown';
        $message = sprintf('[event:%s] user created: %s', $event->getName(), $userEmail);
        error_log($message);
    }
}
