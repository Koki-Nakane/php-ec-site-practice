<?php
/** @var array $cartItems */
/** @var float|int $totalPrice */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ショッピングカート</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 800px; line-height: 1.6; }
        table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
        th, td { border: 1px solid #ccc; padding: .6rem .75rem; text-align: left; }
        th { background: #f7f7f7; }
        .actions { display: flex; gap: .75rem; margin-top: 1rem; }
        button, .link-btn { padding: .65rem 1rem; border: none; border-radius: 4px; background: #0d47a1; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        button:hover, .link-btn:hover { background: #0b3c8c; }
    </style>
</head>
<body>
    <h1>ショッピングカート</h1>

    <?php if (empty($cartItems)): ?>
        <p>カートに商品はありません。</p>
        <div class="actions">
            <a class="link-btn" href="/">商品一覧に戻る</a>
        </div>
    <?php else: ?>
        <table>
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
                        <td><?php echo htmlspecialchars((string) $item['product']->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</td>
                        <td><?php echo htmlspecialchars((string) $item['quantity'], ENT_QUOTES, 'UTF-8'); ?>個</td>
                        <td><?php echo htmlspecialchars((string) ($item['product']->getPrice() * $item['quantity']), ENT_QUOTES, 'UTF-8'); ?>円</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>合計金額: <?php echo htmlspecialchars((string) $totalPrice, ENT_QUOTES, 'UTF-8'); ?>円</h2>

        <div class="actions">
            <form action="/checkout" method="get">
                <button type="submit">注文手続きへ進む</button>
            </form>
            <a class="link-btn" href="/">商品一覧に戻る</a>
        </div>
    <?php endif; ?>
</body>
</html>
