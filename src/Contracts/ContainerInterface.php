<?php

declare(strict_types=1);

namespace App\Contracts;

interface ContainerInterface
{
    /**
     * Register a service definition or an existing instance.
     * When $concrete is a callable, it receives the container and must return the service instance.
     * When $shared=true, the resolved instance is cached and reused.
     */
    public function set(string $id, callable|object $concrete, bool $shared = true): void;

    /**
     * Retrieve a service by id. Should throw if the service is not found.
     *
     * @return mixed
     */
    public function get(string $id): mixed;

    /**
     * Whether the container has a definition or an instance for the given id.
     */
    public function has(string $id): bool;
}
