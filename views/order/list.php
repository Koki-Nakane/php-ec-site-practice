<?php
/** @var string|null $flash */
/** @var string $currentMonthString */
/** @var array $orders */
/** @var string $csrfToken */

use App\Model\Enum\OrderStatus;

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文履歴</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 2rem; }
        form { margin-bottom: 1.5rem; display: flex; gap: 0.5rem; align-items: flex-end; }
        label { display: grid; gap: .25rem; }
        input[type="month"] { padding: .35rem .5rem; }
        button { padding: .45rem .9rem; border: 0; border-radius: 4px; background: #0b5ed7; color: #fff; cursor: pointer; }
        button:hover { background: #0a53be; }
        table { border-collapse: collapse; width: 100%; max-width: 960px; }
        th, td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; }
        th { background: #f7f7f7; }
        .flash { background: #fee; color: #b00; border: 1px solid #fbb; padding: .75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
    </style>
</head>
<body>
    <h1>注文履歴</h1>

    <?php if ($flash !== null): ?>
        <div class="flash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form action="/orders/export" method="post">
        <label>
            対象月
            <input type="month" name="month" value="<?php echo htmlspecialchars($currentMonthString, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($currentMonthString, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit">CSVをダウンロード</button>
    </form>

    <?php if (empty($orders)): ?>
        <p>今月のご注文はまだありません。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>注文ID</th>
                    <th>注文日時</th>
                    <th>ステータス</th>
                    <th>合計金額</th>
                    <th>商品一覧</th>
                    <th>配送先</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $items = []; ?>
                    <?php foreach ($order->getCartItems() as $item): ?>
                        <?php $items[] = sprintf('%s×%d', $item['product']->getName(), $item['quantity']); ?>
                    <?php endforeach; ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $order->getId(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($order->getDate()->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(OrderStatus::label($order->getStatus()), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $order->getTotalPrice(), ENT_QUOTES, 'UTF-8'); ?>円</td>
                        <td><?php echo htmlspecialchars(implode('; ', $items), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($order->getShippingAddress(), ENT_QUOTES, 'UTF-8')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
