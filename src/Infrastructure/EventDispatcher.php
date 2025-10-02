<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Contracts\EventDispatcherInterface;

final class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<int, list<callable>>> */
    private array $listeners = [];

    public function on(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority] ??= [];
        $this->listeners[$eventName][$priority][] = $listener;
    }

    public function dispatch(string $eventName, array $payload = []): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        $event = new Event($eventName, $payload);

        // Sort priorities: higher first
        krsort($this->listeners[$eventName]);

        foreach ($this->listeners[$eventName] as $priority => $listeners) {
            foreach ($listeners as $listener) {
                $listener($event);
                if ($event->isPropagationStopped()) {
                    return;
                }
            }
        }
    }
}
