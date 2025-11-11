<?php

declare(strict_types=1);

use App\Contracts\ContainerInterface;
use App\Contracts\EventDispatcherInterface;
use App\Controller\Admin\DashboardController;
use App\Controller\Admin\OrderController as AdminOrderController;
use App\Controller\Admin\ProductController as AdminProductController;
use App\Controller\Admin\UserController as AdminUserController;
use App\Controller\AuthController;
use App\Controller\CartController;
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
use App\Service\CsrfTokenManager;
use App\Service\OrderCsvExporter;
use App\Service\TemplateRenderer;

// Build and return a DI container with core services.
$container = new Container();

// PDO (shared)
$container->set(\PDO::class, function (): \PDO {
    return Database::getInstance()->getConnection();
}, shared: true);

// Mappers (shared)
$container->set(UserMapper::class, function (ContainerInterface $c): UserMapper {
    return new UserMapper(
        $c->get(\PDO::class),
        $c->get(EventDispatcherInterface::class)
    );
}, shared: true);

$container->set(ProductMapper::class, function (ContainerInterface $c): ProductMapper {
    return new ProductMapper($c->get(\PDO::class));
}, shared: true);

$container->set(OrderMapper::class, function (ContainerInterface $c): OrderMapper {
    return new OrderMapper($c->get(\PDO::class), $c->get(ProductMapper::class));
}, shared: true);

$container->set(OrderCsvExporter::class, function (ContainerInterface $c): OrderCsvExporter {
    return new OrderCsvExporter($c->get(OrderMapper::class));
}, shared: true);

$container->set(CsrfTokenManager::class, function (): CsrfTokenManager {
    return new CsrfTokenManager();
}, shared: true);

$container->set(TemplateRenderer::class, function (): TemplateRenderer {
    return new TemplateRenderer(__DIR__ . '/../views');
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
        $c->get(\PDO::class),
        $c->get(UserMapper::class),
        $c->get(ProductMapper::class),
        $c->get(OrderCsvExporter::class),
        $c->get(CsrfTokenManager::class)
    );
}, shared: true);

$container->set(CartController::class, function (ContainerInterface $c): CartController {
    return new CartController(
        $c->get(ProductMapper::class)
    );
}, shared: true);

$container->set(DashboardController::class, function (ContainerInterface $c): DashboardController {
    return new DashboardController(
        $c->get(TemplateRenderer::class)
    );
}, shared: true);

$container->set(AdminProductController::class, function (ContainerInterface $c): AdminProductController {
    return new AdminProductController(
        $c->get(ProductMapper::class),
        $c->get(TemplateRenderer::class),
        $c->get(CsrfTokenManager::class)
    );
}, shared: true);

$container->set(AdminUserController::class, function (ContainerInterface $c): AdminUserController {
    return new AdminUserController(
        $c->get(UserMapper::class),
        $c->get(TemplateRenderer::class),
        $c->get(CsrfTokenManager::class)
    );
}, shared: true);

$container->set(AdminOrderController::class, function (ContainerInterface $c): AdminOrderController {
    return new AdminOrderController(
        $c->get(OrderMapper::class),
        $c->get(TemplateRenderer::class),
        $c->get(CsrfTokenManager::class)
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
