<?php

declare(strict_types=1);

namespace App\Contracts;

interface EventDispatcherInterface
{
    /**
     * Subscribe a listener to an event name.
     * Higher priority runs earlier.
     *
     * @param non-empty-string $eventName
     */
    public function on(string $eventName, callable $listener, int $priority = 0): void;

    /**
     * Dispatch an event to all listeners.
     *
     * @param non-empty-string $eventName
     * @param array<string,mixed> $payload
     */
    public function dispatch(string $eventName, array $payload = []): void;
}
