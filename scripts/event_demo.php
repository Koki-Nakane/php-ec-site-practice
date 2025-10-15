<?php

declare(strict_types=1);

use App\Contracts\EventDispatcherInterface;

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../config/container.php';

/** @var EventDispatcherInterface $dispatcher */
$dispatcher = $container->get(EventDispatcherInterface::class);

$dispatcher->dispatch('user.created', ['email' => 'taro@example.com']);

echo "Dispatched user.created. Check error_log output.\n";
