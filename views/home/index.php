<?php
/** @var App\Model\Product[] $items */
/** @var bool $isLoggedIn */
/** @var string|null $flash */
/** @var string $csrfToken */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品一覧</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'">
    <link rel="stylesheet" href="/css/home.css">
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
        <form id="post-search-form" class="search-form" action="/posts/search" method="get">
            <label for="post-search" class="visually-hidden">キーワード</label>
            <input type="search" id="post-search" name="q" placeholder="例: セキュリティ 予約" aria-label="記事検索キーワード">
            <button type="submit">検索</button>
        </form>
    </section>
    <h1>商品一覧</h1>
    <?php if ($flash !== null): ?>
        <p class="flash-error">※ <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if (empty($items)):?>
        <p>現在、販売中の商品はありません。</p>
    <?php else:?>
        <ul class="product-list">
            <?php foreach ($items as $product):?>
                <li class="product-card">
                    <h2>
                        <a href="/products/<?php echo $product->getId(); ?>">
                            <?php echo htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </h2>
                    <p>価格: <?php echo htmlspecialchars((string) $product->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</p>
                    <p>在庫: <?php echo htmlspecialchars((string) $product->getStock(), ENT_QUOTES, 'UTF-8'); ?>個</p>
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
</body>
</html>
