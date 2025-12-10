<?php
/** @var array $cartItems */
/** @var float|int $totalPrice */
/** @var string $userName */
/** @var string $userAddress */
/** @var string $csrfToken */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文確認</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 900px; line-height: 1.6; }
        section { margin-bottom: 1.5rem; }
        table { border-collapse: collapse; width: 100%; margin-top: .5rem; }
        th, td { border: 1px solid #ccc; padding: .6rem .75rem; text-align: left; }
        th { background: #f7f7f7; }
        button { padding: .75rem 1.25rem; border: none; border-radius: 4px; background: #0d47a1; color: #fff; font-size: 1rem; cursor: pointer; }
        button:hover { background: #0b3c8c; }
        .actions { display: flex; gap: .75rem; align-items: center; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <h1>注文内容の確認</h1>

    <section>
        <h2>お届け先情報</h2>
        <p>お名前: <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></p>
        <p>ご住所: <?php echo nl2br(htmlspecialchars($userAddress, ENT_QUOTES, 'UTF-8')); ?></p>
    </section>

    <section>
        <h2>ご注文商品</h2>
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
        <h3>合計金額: <?php echo htmlspecialchars((string) $totalPrice, ENT_QUOTES, 'UTF-8'); ?>円</h3>
    </section>

    <form action="/place_order" method="post">
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="actions">
            <button type="submit">この内容で注文を確定する</button>
            <a href="/cart">カートに戻る</a>
        </div>
    </form>
</body>
</html>
