<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\ProductMapper;
use App\Service\CsrfTokenManager;

final class HomeController
{
    public function __construct(
        private ProductMapper $products,
        private CsrfTokenManager $csrfTokens
    ) {
    }

    public function index(Request $request): Response
    {
        $items = $this->products->findAll();
        $isLoggedIn = isset($_SESSION['user_id']);
        $flash = $_SESSION['error_message'] ?? null;
        unset($_SESSION['error_message']);
        $csrfToken = $this->csrfTokens->issue('cart_form');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>商品一覧</title>
            <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'">
            <style>
                body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; margin: 2rem; }
                .site-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
                .site-header a { color: #0b5ed7; text-decoration: none; font-weight: 600; }
                .site-header a:hover { text-decoration: underline; }
                .search-card { margin-bottom: 2rem; padding: 1.25rem; border: 1px solid #e5e7eb; border-radius: 8px; background: #f8fafc; }
                .search-card h2 { margin-top: 0; margin-bottom: .5rem; font-size: 1.1rem; }
                .search-form { display: flex; gap: .5rem; align-items: center; }
                .search-form input[type="search"] { flex: 1; padding: .65rem .75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; }
                .search-form button { padding: .65rem 1rem; border: none; border-radius: 6px; background: #0b5ed7; color: #fff; font-weight: 600; cursor: pointer; }
                .search-form button:hover { background: #0a53be; }
                .search-result { margin-top: .5rem; font-size: .95rem; color: #334155; }
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
            <section class="search-card">
                <h2>ブログ記事をキーワード検索</h2>
                <form id="post-search-form" class="search-form">
                    <label for="post-search" class="visually-hidden">キーワード</label>
                    <input type="search" id="post-search" name="q" placeholder="例: セキュリティ 予約" aria-label="記事検索キーワード">
                    <button type="submit">検索</button>
                </form>
                <div id="post-search-result" class="search-result"></div>
            </section>
            <h1>商品一覧</h1>
            <?php if ($flash !== null): ?>
                <p style="color:red;">※ <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if (empty($items)):?>
                <p>現在、販売中の商品はありません。</p>
            <?php else:?>
                <ul>
                    <?php foreach ($items as $product):?>
                        <li>
                            <h2>
                                <a href="/products/<?php echo $product->getId(); ?>">
                                    <?php echo htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </h2>
                            <p>価格: <?php echo htmlspecialchars((string)$product->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</p>
                            <p>在庫: <?php echo htmlspecialchars((string)$product->getStock(), ENT_QUOTES, 'UTF-8'); ?>個</p>
                            <p><?php echo nl2br(htmlspecialchars($product->getDescription(), ENT_QUOTES, 'UTF-8')); ?></p>
                            <p><a href="/products/<?php echo $product->getId(); ?>#reviews">レビューを確認する</a></p>
                            <form action="/add_to_cart" method="post">
                                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="product_id" value="<?php echo $product->getId(); ?>">
                                <label for="quantity-<?php echo $product->getId(); ?>">数量:</label>
                                <input type="number" id="quantity-<?php echo $product->getId(); ?>" name="quantity" value="1" min="1">
                                <button type="submit">カートに入れる</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <script>
            (() => {
                const form = document.getElementById('post-search-form');
                const input = document.getElementById('post-search');
                const result = document.getElementById('post-search-result');

                if (!form || !input || !result) {
                    return;
                }

                const renderMessage = (text, isError = false) => {
                    result.textContent = text;
                    result.style.color = isError ? '#b91c1c' : '#334155';
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const keyword = (input.value || '').trim();
                    if (keyword === '') {
                        renderMessage('キーワードを入力してください。', true);
                        return;
                    }

                    renderMessage('検索中です...');
                    try {
                        const res = await fetch(`/posts?q=${encodeURIComponent(keyword)}`, { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();

                        if (!res.ok || !data || !Array.isArray(data.data)) {
                            throw new Error('検索結果の取得に失敗しました。');
                        }

                        if (data.data.length === 0) {
                            renderMessage('該当する記事は見つかりませんでした。');
                            return;
                        }

                        const titles = data.data.map((p) => p.title || '(タイトルなし)').slice(0, 5);
                        renderMessage(`検索結果: ${data.data.length}件 / 先頭5件: ${titles.join(' / ')}`);
                    } catch (e) {
                        const message = e instanceof Error ? e.message : '検索に失敗しました。';
                        renderMessage(message, true);
                    }
                });
            })();
            </script>
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
