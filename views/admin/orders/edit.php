<?php
/** @var App\Model\Order $order */
/** @var array<int,string> $statuses */
/** @var array<int,array{type:string,message:string}> $flashes */
/** @var array{input:array<string,string|null>,errors:array<int,string>}|null $form */
/** @var string $updateToken */
/** @var string $toggleDeletionToken */

$input = $form['input'] ?? [
    'shipping_address' => $order->getShippingAddress(),
    'status' => (string) $order->getStatus(),
];
$errors = $form['errors'] ?? [];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文編集 #<?= htmlspecialchars((string) $order->getId(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 2rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        h1 { margin: 0; font-size: 1.75rem; }
        .summary { padding: .75rem 1rem; border-radius: 6px; background: #f5f5f5; margin-bottom: 1.25rem; display: grid; gap: .5rem; }
        .summary-item { display: flex; gap: .75rem; align-items: baseline; }
        .summary-item span:first-child { width: 9rem; color: #555; font-weight: 600; }
        .flash { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .flash-success { background: #e6f4ea; color: #1e4620; }
        .flash-error { background: #fdecea; color: #611a15; }
        .error-list { margin: 0 0 1rem; padding-left: 1.25rem; color: #611a15; }
        form { max-width: 720px; display: grid; gap: 1rem; }
        label { display: grid; gap: .35rem; font-weight: 600; }
        textarea, select { padding: .5rem .65rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        textarea { min-height: 140px; resize: vertical; }
        .actions { display: flex; gap: .75rem; }
        button { padding: .5rem 1rem; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #0b5ed7; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-danger { background: #c53030; color: #fff; }
        .items-table { border-collapse: collapse; width: 100%; margin: 2rem 0; }
        .items-table th, .items-table td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; }
        .items-table th { background: #f7f7f7; }
        .status-tag { display: inline-flex; align-items: center; gap: .35rem; padding: .15rem .5rem; border-radius: 999px; background: #eef2ff; color: #1d3b8b; font-size: .85rem; }
        .status-tag--deleted { background: #f4f4f5; color: #6b7280; font-style: italic; }
        .metadata { display: grid; gap: .5rem; margin-top: .5rem; color: #555; font-size: .95rem; }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>注文編集</h1>
            <p style="margin: .25rem 0 0; color: #555;">ID: <?= htmlspecialchars((string) $order->getId(), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <nav>
            <a href="/admin/orders">一覧へ戻る</a>
        </nav>
    </header>

    <div class="summary">
        <div class="summary-item">
            <span>ステータス</span>
            <span class="status-tag">
                <?= htmlspecialchars($statuses[$order->getStatus()] ?? '不明', ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
        <div class="summary-item">
            <span>金額</span>
            <span><?= htmlspecialchars(number_format($order->getTotalPrice(), 2), ENT_QUOTES, 'UTF-8'); ?> 円</span>
        </div>
        <div class="summary-item">
            <span>ユーザー</span>
            <span>
                <?= htmlspecialchars($order->getUser()->getName(), ENT_QUOTES, 'UTF-8'); ?>
                (<?= htmlspecialchars($order->getUser()->getEmail(), ENT_QUOTES, 'UTF-8'); ?>)
            </span>
        </div>
        <div class="summary-item">
            <span>状態</span>
            <span>
                <?php if ($order->isDeleted()): ?>
                    <span class="status-tag status-tag--deleted">削除済</span>
                    <?php if ($order->getDeletedAt() !== null): ?>
                        <?= htmlspecialchars($order->getDeletedAt()->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="status-tag">有効</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="summary-item">
            <span>日時</span>
            <span class="metadata">
                <span>作成: <?= htmlspecialchars($order->getCreatedAt()->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></span>
                <span>更新: <?= htmlspecialchars($order->getUpdatedAt()->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></span>
            </span>
        </div>
    </div>

    <?php foreach ($flashes as $flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
            <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endforeach; ?>

    <?php if ($errors !== []): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form action="/admin/orders/update" method="post">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($updateToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $order->getId(), ENT_QUOTES, 'UTF-8'); ?>">

        <label>
            ステータス
            <select name="status" required>
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= (string)$value === $input['status'] ? 'selected' : ''; ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            配送先住所
            <textarea name="shipping_address" maxlength="1000"><?= htmlspecialchars((string) $input['shipping_address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>

        <div class="actions">
            <button type="submit" class="btn-primary">更新する</button>
        </div>
    </form>

    <table class="items-table">
        <thead>
            <tr>
                <th>商品名</th>
                <th>単価</th>
                <th>数量</th>
                <th>小計</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order->getCartItems() as $item): ?>
                <?php $unitPrice = isset($item['price']) ? (float) $item['price'] : $item['product']->getPrice(); ?>
                <tr>
                    <td><?= htmlspecialchars($item['product']->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars(number_format($unitPrice, 2), ENT_QUOTES, 'UTF-8'); ?> 円</td>
                    <td><?= htmlspecialchars((string) $item['quantity'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars(number_format($unitPrice * $item['quantity'], 2), ENT_QUOTES, 'UTF-8'); ?> 円</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form action="/admin/orders/toggle-deletion" method="post" style="margin-top: 1.5rem;">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($toggleDeletionToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $order->getId(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="<?= $order->isDeleted() ? 'btn-secondary' : 'btn-danger'; ?>">
            <?= $order->isDeleted() ? '注文を復元する' : '注文を削除する'; ?>
        </button>
    </form>
</body>
</html>
