<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\ProductMapper;

final class HomeController
{
    public function __construct(private ProductMapper $products)
    {
    }

    public function index(Request $request): Response
    {
        $items = $this->products->findAll();
        $isLoggedIn = isset($_SESSION['user_id']);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>商品一覧</title>
            <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
            <style>
                body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; margin: 2rem; }
                .site-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
                .site-header a { color: #0b5ed7; text-decoration: none; font-weight: 600; }
                .site-header a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <header class="site-header">
                <div><a href="/">EC Practice</a></div>
                <nav>
                    <?php if ($isLoggedIn): ?>
                        <a href="/orders">注文履歴</a>
                    <?php else: ?>
                        <a href="/login">ログイン</a>
                    <?php endif; ?>
                </nav>
            </header>
            <h1>商品一覧</h1>
            <?php if (empty($items)):?>
                <p>現在、販売中の商品はありません。</p>
            <?php else:?>
                <ul>
                    <?php foreach ($items as $product):?>
                        <li>
                            <h2><?php echo htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p>価格: <?php echo htmlspecialchars((string)$product->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</p>
                            <p>在庫: <?php echo htmlspecialchars((string)$product->getStock(), ENT_QUOTES, 'UTF-8'); ?>個</p>
                            <p><?php echo nl2br(htmlspecialchars($product->getDescription(), ENT_QUOTES, 'UTF-8')); ?></p>
                            <form action="/add_to_cart" method="post">
                                <input type="hidden" name="product_id" value="<?php echo $product->getId(); ?>">
                                <label for="quantity-<?php echo $product->getId(); ?>">数量:</label>
                                <input type="number" id="quantity-<?php echo $product->getId(); ?>" name="quantity" value="1" min="1">
                                <button type="submit">カートに入れる</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function apiProducts(Request $request): Response
    {
        $items = $this->products->findAll();
        $rows = [];
        foreach ($items as $p) {
            $rows[] = [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'price' => $p->getPrice(),
                'stock' => $p->getStock(),
                'description' => $p->getDescription(),
            ];
        }
        return Response::json(['items' => $rows]);
    }
}
