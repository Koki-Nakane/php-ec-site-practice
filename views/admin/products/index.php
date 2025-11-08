<?php
/** @var App\Model\Product[] $products */
/** @var array<int,array{type:string,message:string}> $flashes */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品管理</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 2rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        a { color: #0b5ed7; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; }
        th { background: #f7f7f7; }
        .status { display: inline-flex; align-items: center; gap: .25rem; font-size: .9rem; }
        .status--inactive { color: #b00; }
        .status--deleted { color: #555; font-style: italic; }
    </style>
</head>
<body>
    <header>
        <div>
            <h1 style="margin: 0; font-size: 1.75rem;">商品一覧</h1>
            <p style="margin: .25rem 0 0; color: #555;">公開・在庫・削除状態を確認できます。</p>
        </div>
        <nav>
            <a href="/admin">ダッシュボードへ戻る</a>
        </nav>
    </header>

    <?php if (!empty($flashes)): ?>
        <div style="margin-bottom: 1rem;">
            <?php foreach ($flashes as $flash): ?>
                <div style="padding: .75rem 1rem; border-radius: 6px; margin-bottom: .5rem; background: <?= $flash['type'] === 'success' ? '#e6f4ea' : '#fdecea'; ?>; color: <?= $flash['type'] === 'success' ? '#1e4620' : '#611a15'; ?>;">
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($products === []): ?>
        <p>登録されている商品はありません。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>商品名</th>
                    <th>価格</th>
                    <th>在庫</th>
                    <th>ステータス</th>
                    <th>更新日時</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars(number_format($product->getPrice(), 2), ENT_QUOTES, 'UTF-8'); ?> 円</td>
                        <td><?= htmlspecialchars((string) $product->getStock(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="status<?= $product->isActive() ? '' : ' status--inactive'; ?>">
                                <?= $product->isActive() ? '公開中' : '非公開'; ?>
                            </span>
                            <?php if ($product->isDeleted()): ?>
                                <span class="status status--deleted">(削除済)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($product->getUpdatedAt()->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><a href="/admin/products/edit?id=<?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8'); ?>">編集</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
