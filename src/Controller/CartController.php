<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\ProductMapper;
use App\Model\Cart;

final class CartController
{
    public function __construct(private ProductMapper $products)
    {
    }

    public function show(Request $request): Response
    {
        $cart = ($_SESSION['cart'] ?? null);
        if (!($cart instanceof Cart)) {
            $cart = new Cart();
        }

        $cartItems = $cart->getItems();
        $totalPrice = $cart->getTotalPrice();

        ob_start();
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

            <form action="/checkout" method="get">
                <button type="submit">注文手続きへ進む</button>
            </form>

            <p><a href="/">商品一覧に戻る</a></p>

        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function add(Request $request): Response
    {
        try {
            $productId = filter_var($request->body['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity  = filter_var($request->body['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
                throw new \InvalidArgumentException('無効な商品IDまたは数量です。');
            }

            $product = $this->products->find($productId);
            if ($product === null) {
                throw new \RuntimeException('指定された商品が見つかりません。');
            }

            $cart = ($_SESSION['cart'] ?? null);
            if (!($cart instanceof Cart)) {
                $cart = new Cart();
            }

            $cart->addProduct($product, $quantity);
            $_SESSION['cart'] = $cart;

        } catch (\Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            return Response::redirect('/', 303);
        }

        return Response::redirect('/cart', 303);
    }
}
