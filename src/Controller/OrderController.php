<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\OrderMapper;
use App\Mapper\ProductMapper;
use App\Mapper\UserMapper;
use App\Model\Cart;
use App\Model\Order;
use PDO;

final class OrderController
{
    public function __construct(
        private PDO $pdo,
        private UserMapper $users,
        private ProductMapper $products,
    ) {
    }

    public function checkout(Request $request): Response
    {
        $cart = ($_SESSION['cart'] ?? null);
        if (!($cart instanceof Cart)) {
            $cart = new Cart();
        }

        if (empty($cart->getItems())) {
            $_SESSION['error_message'] = 'カートが空です。';
            return Response::redirect('/');
        }

        $userId = $_SESSION['user_id'] ?? null;
        $user = $userId ? $this->users->find((int)$userId) : null;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>注文確認</title>
        </head>
        <body>
            <h1>注文内容の確認</h1>

            <h2>お届け先情報</h2>
            <p>お名前: <?php echo htmlspecialchars($user?->getName() ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <p>ご住所: <?php echo htmlspecialchars($user?->getAddress() ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

            <h2>ご注文商品</h2>
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
                    <?php foreach ($cart->getItems() as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product']->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$item['product']->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</td>
                            <td><?php echo htmlspecialchars((string)$item['quantity'], ENT_QUOTES, 'UTF-8'); ?>個</td>
                            <td><?php echo htmlspecialchars((string)($item['product']->getPrice() * $item['quantity']), ENT_QUOTES, 'UTF-8'); ?>円</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h3>合計金額: <?php echo htmlspecialchars((string)$cart->getTotalPrice(), ENT_QUOTES, 'UTF-8'); ?>円</h3>

            <form action="/place_order" method="post">
                <button type="submit">この内容で注文を確定する</button>
            </form>

        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function place(Request $request): Response
    {
        $cart = ($_SESSION['cart'] ?? null);
        if (!($cart instanceof Cart) || empty($cart->getItems())) {
            return Response::redirect('/');
        }

        $this->pdo->beginTransaction();
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $user = $userId ? $this->users->find((int)$userId) : null;
            if ($user === null) {
                throw new \RuntimeException('ユーザー情報が見つかりません。');
            }

            $order = new Order($user, $cart);

            $orderMapper = new OrderMapper($this->pdo, $this->products);
            $orderMapper->save($order);

            foreach ($order->getCartItems() as $item) {
                $this->products->decreaseStock($item['product']->getId(), $item['quantity']);
            }

            unset($_SESSION['cart']);
            $_SESSION['latest_order'] = $order;

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $_SESSION['error_message'] = '注文処理中にエラーが発生しました: ' . $e->getMessage();
            return Response::redirect('/checkout');
        }

        return Response::redirect('/order_complete');
    }
}
