<?php

declare(strict_types=1);

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\OrderController as AdminOrderController;
use App\Controller\Admin\ProductController as AdminProductController;
use App\Controller\Admin\UserController as AdminUserController;
use App\Controller\AuthController;
use App\Controller\CartController;
use App\Controller\HomeController;
use App\Controller\OrderController;
use App\Http\Request;
use App\Http\Response;
use App\Http\ResponseEmitter;
use App\Infrastructure\Middleware\AdminAuthMiddleware;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Infrastructure\Middleware\ErrorHandlerMiddleware;
use App\Infrastructure\Middleware\LoggingMiddleware;
use App\Infrastructure\Middleware\Pipeline;

require_once __DIR__ . '/../vendor/autoload.php';
session_start();

$request = Request::fromGlobals();

// コンテナから依存を取得
/** @var App\Infrastructure\Container $container */
$container = require __DIR__ . '/../config/container.php';

/** @var AuthController $auth */
$auth = $container->get(AuthController::class);
/** @var HomeController $home */
$home = $container->get(HomeController::class);
/** @var OrderController $order */
$order = $container->get(OrderController::class);
/** @var CartController $cart */
$cart = $container->get(CartController::class);
/** @var DashboardController $adminDashboard */
$adminDashboard = $container->get(DashboardController::class);
/** @var AdminProductController $adminProducts */
$adminProducts = $container->get(AdminProductController::class);
/** @var AdminOrderController $adminOrders */
$adminOrders = $container->get(AdminOrderController::class);
/** @var AdminUserController $adminUsers */
$adminUsers = $container->get(AdminUserController::class);

// ルート定義を外部から読み込む
$routesFactory = require __DIR__ . '/../config/routes.php';

$routes = $routesFactory($home, $order, $cart, $auth, $adminDashboard, $adminProducts, $adminOrders, $adminUsers);

// ルート解決
$route = null;
foreach ($routes as $r) {
    if ($r[0] === $request->method && $r[1] === $request->path) {
        $route = $r;
        break;
    }
}

if ($route === null) {
    ResponseEmitter::emit(new Response(404, 'Not Found'));
    exit;
}

[$m, $p, $tag, $handler] = $route;

// パイプライン構築（右から左へ）
$pipeline = new Pipeline();

// 最外層でエラーハンドリング
$isApi = str_starts_with($tag, 'api:');
$pipeline->pipe(new ErrorHandlerMiddleware($isApi));

// ロギングは常に実施
$pipeline->pipe(new LoggingMiddleware());

// 認証が必要な場合
if ($tag === 'web:auth') {
    $pipeline->pipe(new AuthMiddleware($auth, '/login', false));
}
if ($tag === 'api:auth') {
    $pipeline->pipe(new AuthMiddleware($auth, '/login', true));
}
if ($tag === 'web:admin') {
    $pipeline->pipe(new AuthMiddleware($auth, '/login', false));
    $pipeline->pipe(new AdminAuthMiddleware($auth));
}

$response = $pipeline->process($request, $handler);
ResponseEmitter::emit($response);
