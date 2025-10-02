<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Contracts\ContainerInterface;
use Closure;
use RuntimeException;

final class Container implements ContainerInterface
{
    /** @var array<string, array{factory: callable, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function set(string $id, callable|object $concrete, bool $shared = true): void
    {
        if (is_object($concrete) && !($concrete instanceof Closure)) {
            $this->instances[$id] = $concrete;
            return;
        }

        $this->bindings[$id] = [
            'factory' => $concrete,
            'shared'  => $shared,
        ];
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (!isset($this->bindings[$id])) {
            throw new RuntimeException("Service not found: {$id}");
        }

        $def = $this->bindings[$id];
        $object = ($def['factory'])($this);

        if ($def['shared'] && is_object($object)) {
            $this->instances[$id] = $object;
        }
        return $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }
}
