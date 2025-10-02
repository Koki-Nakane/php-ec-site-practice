<?php

declare(strict_types=1);

use App\Model\Cart;

require_once __DIR__ . '/../vendor/autoload.php';
session_start();

if (isset($_SESSION['cart']) && $_SESSION['cart'] instanceof Cart) {
    $cart = $_SESSION['cart'];
} else {
    $cart = new Cart();
}

$cartItems = $cart->getItems();
$totalPrice = $cart->getTotalPrice();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ショッピングカート</title>
</head>
<body>
    <h1>ショッピングカート</h1>

    <?php if (empty($cartItems)): ?>
        <p>カートに商品はありません。</p>
    <?php else: ?>
        <table border="1">
            <thead>
                <tr>
                    <th>商品名</th>
                    <th>価格</th>
                    <th>数量</th>
                    <th>小計</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product']->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)$item['product']->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</td>
                        <td><?php echo htmlspecialchars((string)$item['quantity'], ENT_QUOTES, 'UTF-8'); ?>個</td>
                        <td><?php echo htmlspecialchars((string)($item['product']->getPrice() * $item['quantity']), ENT_QUOTES, 'UTF-8'); ?>円</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>合計金額: <?php echo htmlspecialchars((string)$totalPrice, ENT_QUOTES, 'UTF-8'); ?>円</h2>
    <?php endif; ?>

    <form action="/checkout.php" method="get">
        <button type="submit">注文手続きへ進む</button>
    </form>

    <p><a href="/index.php">商品一覧に戻る</a></p>

</body>
</html>
