<?php

declare(strict_types=1);

namespace App\Listener;

use App\Infrastructure\Event;

final class SendWelcomeEmailListener
{
    public function __invoke(Event $event): void
    {
        $payload = $event->getPayload();
        $email = (string)($payload['email'] ?? '');
        if ($email === '') {
            return;
        }
        // 実運用ではここでメール送信を行う。演習ではログに出す。
        error_log(sprintf('Welcome email queued to %s', $email));
    }
}
