<?php

declare(strict_types=1);

use App\Contracts\ContainerInterface;
use App\Contracts\EventDispatcherInterface;
use App\Controller\AuthController;
use App\Controller\HomeController;
use App\Controller\OrderController;
use App\Infrastructure\Container;
use App\Infrastructure\EventDispatcher;
use App\Listener\LogUserCreatedListener;
use App\Listener\SendWelcomeEmailListener;
use App\Mapper\OrderMapper;
use App\Mapper\ProductMapper;
use App\Mapper\UserMapper;
use App\Model\Database;
use PDO;

// Build and return a DI container with core services.
$container = new Container();

// PDO (shared)
$container->set(PDO::class, function (): PDO {
    return Database::getInstance()->getConnection();
}, shared: true);

// Mappers (shared)
$container->set(UserMapper::class, function (ContainerInterface $c): UserMapper {
    return new UserMapper(
        $c->get(PDO::class),
        $c->get(EventDispatcherInterface::class)
    );
}, shared: true);

$container->set(ProductMapper::class, function (ContainerInterface $c): ProductMapper {
    return new ProductMapper($c->get(PDO::class));
}, shared: true);

$container->set(OrderMapper::class, function (ContainerInterface $c): OrderMapper {
    return new OrderMapper($c->get(PDO::class), $c->get(ProductMapper::class));
}, shared: true);

// Controllers (shared)
$container->set(AuthController::class, function (ContainerInterface $c): AuthController {
    return new AuthController($c->get(UserMapper::class));
}, shared: true);

$container->set(HomeController::class, function (ContainerInterface $c): HomeController {
    return new HomeController($c->get(ProductMapper::class));
}, shared: true);

$container->set(OrderController::class, function (ContainerInterface $c): OrderController {
    return new OrderController(
        $c->get(PDO::class),
        $c->get(UserMapper::class),
        $c->get(ProductMapper::class)
    );
}, shared: true);

// Event dispatcher and default listeners
$container->set(EventDispatcherInterface::class, function (): EventDispatcherInterface {
    return new EventDispatcher();
}, shared: true);

// Register default listeners once
/** @var EventDispatcherInterface $dispatcher */
$dispatcher = $container->get(EventDispatcherInterface::class);
$dispatcher->on('user.created', new LogUserCreatedListener(), priority: 10);
$dispatcher->on('user.created', new SendWelcomeEmailListener(), priority: 0);

return $container;
