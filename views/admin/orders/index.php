<?php
/** @var App\Model\Order[] $orders */
/** @var array<int,string> $statuses */
/** @var string $selectedStatus */
/** @var string $deletedFilter */
/** @var array<int,array{type:string,message:string}> $flashes */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文管理</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 2rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        a { color: #0b5ed7; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; vertical-align: top; }
        th { background: #f7f7f7; }
        .filters { display: flex; gap: 1rem; margin-bottom: 1.25rem; }
        .filters form { display: flex; gap: .75rem; align-items: flex-end; flex-wrap: wrap; }
        .filters label { display: grid; gap: .35rem; font-weight: 600; color: #333; }
        select { padding: .4rem .55rem; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: .45rem .9rem; border: none; border-radius: 4px; background: #0b5ed7; color: #fff; cursor: pointer; }
        button:hover { background: #0a53be; }
        .flash { padding: .75rem 1rem; border-radius: 6px; margin-bottom: .5rem; }
        .flash-success { background: #e6f4ea; color: #1e4620; }
        .flash-error { background: #fdecea; color: #611a15; }
        .status-tag { display: inline-flex; align-items: center; gap: .35rem; padding: .15rem .5rem; border-radius: 999px; background: #eef2ff; color: #1d3b8b; font-size: .85rem; }
        .status-tag--deleted { background: #f4f4f5; color: #6b7280; font-style: italic; }
        .user-info { display: flex; flex-direction: column; gap: .25rem; }
    </style>
</head>
<body>
    <header>
        <div>
            <h1 style="margin: 0; font-size: 1.75rem;">注文一覧</h1>
            <p style="margin: .25rem 0 0; color: #555;">注文状況・配送先・担当ユーザーを確認できます。</p>
        </div>
        <nav>
            <a href="/admin">ダッシュボードへ戻る</a>
        </nav>
    </header>

    <?php foreach ($flashes as $flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
            <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endforeach; ?>

    <section class="filters">
        <form method="get" action="/admin/orders">
            <label>
                ステータス
                <select name="status">
                    <option value="all" <?= $selectedStatus === 'all' ? 'selected' : ''; ?>>すべて</option>
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= (string)$value === $selectedStatus ? 'selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                削除状態
                <select name="deleted">
                    <option value="all" <?= $deletedFilter === 'all' ? 'selected' : ''; ?>>すべて</option>
                    <option value="active" <?= $deletedFilter === 'active' ? 'selected' : ''; ?>>有効</option>
                    <option value="deleted" <?= $deletedFilter === 'deleted' ? 'selected' : ''; ?>>削除済</option>
                </select>
            </label>
            <button type="submit">絞り込む</button>
        </form>
    </section>

    <?php if ($orders === []): ?>
        <p>条件に一致する注文は見つかりませんでした。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>注文日時</th>
                    <th>ステータス</th>
                    <th>合計金額</th>
                    <th>ユーザー</th>
                    <th>商品一覧</th>
                    <th>配送先</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $items = []; ?>
                    <?php foreach ($order->getCartItems() as $item): ?>
                        <?php $items[] = sprintf('%s×%d', $item['product']->getName(), $item['quantity']); ?>
                    <?php endforeach; ?>
                    <?php $user = $order->getUser(); ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $order->getId(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($order->getDate()->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="status-tag">
                                <?= htmlspecialchars($statuses[$order->getStatus()] ?? '不明', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(number_format($order->getTotalPrice(), 2), ENT_QUOTES, 'UTF-8'); ?> 円</td>
                        <td>
                            <div class="user-info">
                                <span><?= htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8'); ?><?= $user->isDeleted() ? '（削除済）' : ''; ?></span>
                                <span><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8'); ?></span>
                                <a href="/admin/users/edit?id=<?= htmlspecialchars((string) $user->getId(), ENT_QUOTES, 'UTF-8'); ?>">ユーザー詳細</a>
                            </div>
                        </td>
                        <td><?= htmlspecialchars(implode('; ', $items), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= nl2br(htmlspecialchars($order->getShippingAddress(), ENT_QUOTES, 'UTF-8')); ?></td>
                        <td>
                            <?php if ($order->isDeleted()): ?>
                                <span class="status-tag status-tag--deleted">削除済</span><br>
                                <?= $order->getDeletedAt() ? htmlspecialchars($order->getDeletedAt()->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') : ''; ?>
                            <?php else: ?>
                                <span class="status-tag">有効</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/admin/orders/edit?id=<?= htmlspecialchars((string) $order->getId(), ENT_QUOTES, 'UTF-8'); ?>">詳細</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
