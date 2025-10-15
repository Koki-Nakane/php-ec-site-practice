<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class Event
{
    /** @var array<string,mixed> */
    private array $payload;

    private bool $propagationStopped = false;

    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(private string $name, array $payload = [])
    {
        $this->payload = $payload;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string,mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
