<?php

/* 注文確定処理 (place_order.php) */
declare(strict_types=1);

use App\Controller\AuthController;
use App\Mapper\OrderMapper;
use App\Mapper\ProductMapper;
use App\Mapper\UserMapper;
use App\Model\Cart;
use App\Model\Database;
use App\Model\Order;

require_once __DIR__ . '/../vendor/autoload.php';
session_start();

$pdo = Database::getInstance()->getConnection();
$userMapper = new UserMapper($pdo);
$authController = new AuthController($userMapper);
$productMapper = new ProductMapper($pdo);

if (!$authController->isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

if (isset($_SESSION['cart']) && $_SESSION['cart'] instanceof Cart) {
    $cart = $_SESSION['cart'];
} else {
    header('Location: /index.php');
    exit;
}

if (empty($cart->getItems())) {
    header('Location: /index.php');
    exit;
}

$pdo->beginTransaction();

try {
    $user = $userMapper->find($_SESSION['user_id']);
    if ($user === null) {
        throw new Exception('ユーザー情報が見つかりません。');
    }

    $order = new Order($user, $cart);

    $orderMapper = new OrderMapper($pdo, $productMapper);
    $orderMapper->save($order);

    foreach ($order->getCartItems() as $item) {
        $productMapper->decreaseStock($item['product']->getId(), $item['quantity']);
    }

    unset($_SESSION['cart']);
    $_SESSION['latest_order'] = $order;

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = '注文処理中にエラーが発生しました: ' . $e->getMessage();
    header('Location: /checkout.php');
    exit;
}

header('Location: /order_complete.php');
exit;
