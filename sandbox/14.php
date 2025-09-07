<?php

/* 14. セッションを使ったカートの実装:
Cart オブジェクトをセッションに保存し、ユーザーがページを移動してもカートの内容が保持されるようにしてください。
*/

declare(strict_types=1);

use App\Mapper\ProductMapper;
use App\Model\Cart;

require_once __DIR__ . '/vendor/autoload.php';

session_start();

$pdo = Database::getInstance()->getConnection();
$productMapper = new ProductMapper($pdo);

// sessionにcartがあれば取り出し、なければ作る
if (isset($_SESSION['cart']) && $_SESSION['cart'] instanceof Cart) {
    $cart = $_SESSION['cart'];
} else {
    $cart = new Cart();
}

$_SESSION['cart'] = $cart;
