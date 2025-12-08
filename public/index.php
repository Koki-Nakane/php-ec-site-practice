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
use App\Controller\PasswordResetController;
use App\Controller\PostalCodeController;
use App\Controller\PostController;
use App\Controller\ProductReviewController;
use App\Controller\SecurityController;
use App\Http\Request;
use App\Http\Response;
use App\Http\ResponseEmitter;
use App\Infrastructure\Middleware\AdminAuthMiddleware;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Infrastructure\Middleware\CommentAuthorOrAdminMiddleware;
use App\Infrastructure\Middleware\CsrfProtectionMiddleware;
use App\Infrastructure\Middleware\ErrorHandlerMiddleware;
use App\Infrastructure\Middleware\LoggingMiddleware;
use App\Infrastructure\Middleware\Pipeline;
use App\Infrastructure\Middleware\PostAuthorOrAdminMiddleware;
use App\Service\CsrfTokenManager;

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
/** @var PostController $postsController */
$postsController = $container->get(PostController::class);
/** @var SecurityController $securityController */
$securityController = $container->get(SecurityController::class);
/** @var PasswordResetController $passwordResetController */
$passwordResetController = $container->get(PasswordResetController::class);
/** @var ProductReviewController $productReviewController */
$productReviewController = $container->get(ProductReviewController::class);
/** @var PostalCodeController $postalCodeController */
$postalCodeController = $container->get(PostalCodeController::class);
/** @var CsrfTokenManager $csrfTokens */
$csrfTokens = $container->get(CsrfTokenManager::class);

// ルート定義を外部から読み込む
$routesFactory = require __DIR__ . '/../config/routes.php';

$routes = $routesFactory($home, $order, $cart, $auth, $adminDashboard, $adminProducts, $adminOrders, $adminUsers, $postsController, $securityController, $passwordResetController, $productReviewController, $postalCodeController);

// ルート解決
$route = null;
foreach ($routes as $r) {
    [$method, $path] = $r;
    $isPattern = $r[4] ?? false;

    if ($method !== $request->method) {
        continue;
    }

    $matched = $isPattern
        ? preg_match($path, $request->path) === 1
        : $path === $request->path;

    if ($matched) {
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
if ($tag === 'api:auth:owner') {
    $pipeline->pipe(new AuthMiddleware($auth, '/login', true));
    $pipeline->pipe($container->get(PostAuthorOrAdminMiddleware::class));
}
if ($tag === 'api:auth:comment-owner') {
    $pipeline->pipe(new AuthMiddleware($auth, '/login', true));
    $pipeline->pipe($container->get(CommentAuthorOrAdminMiddleware::class));
}
if ($tag === 'web:admin') {
    $pipeline->pipe(new AuthMiddleware($auth, '/login', false));
    $pipeline->pipe(new AdminAuthMiddleware($auth));
}

if (str_starts_with($tag, 'api:') && $tag !== 'api:public') {
    $pipeline->pipe(new CsrfProtectionMiddleware($csrfTokens, 'api'));
} elseif ($tag === 'web:admin') {
    $pipeline->pipe(new CsrfProtectionMiddleware($csrfTokens, 'admin'));
} elseif ($tag === 'web:auth' || $tag === 'web:public') {
    $pipeline->pipe(new CsrfProtectionMiddleware($csrfTokens, 'web'));
}

$response = $pipeline->process($request, $handler);
ResponseEmitter::emit($response);
