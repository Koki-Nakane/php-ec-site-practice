<?php

declare(strict_types=1);

use App\Mapper\ProductMapper;
use App\Model\Cart;
use App\Model\Database;

require_once __DIR__ . '/../vendor/autoload.php';
session_start();

try {
    $pdo = Database::getInstance()->getConnection();
    $productMapper = new ProductMapper($pdo);

    if (isset($_SESSION['cart']) && $_SESSION['cart'] instanceof Cart) {
        $cart = $_SESSION['cart'];
    } else {
        $cart = new Cart();
    }

    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
        throw new Exception('無効な商品IDまたは数量です。');
    }

    $product = $productMapper->find($productId);
    if ($product === null) {
        throw new Exception('指定された商品が見つかりません。');
    }

    $cart->addProduct($product, $quantity);

    $_SESSION['cart'] = $cart;

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: /index.php');
    exit;
}

header('Location: /cart.php');
exit;
