<?php
/** @var App\Model\Product $product */
/** @var array<int,array{type:string,message:string}> $flashes */
/** @var array{input:array<string,string|null>,errors:array<int,string>}|null $form */
/** @var string $updateToken */
/** @var string $toggleToken */

$input = $form['input'] ?? [
    'name' => $product->getName(),
    'price' => number_format($product->getPrice(), 2, '.', ''),
    'stock' => (string) $product->getStock(),
    'description' => $product->getDescription(),
    'is_active' => $product->isActive() ? '1' : '0',
];
$errors = $form['errors'] ?? [];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品編集 #<?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 2rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        form { max-width: 640px; display: grid; gap: 1rem; }
        label { display: grid; gap: .35rem; }
        input[type="text"], input[type="number"], textarea, select { padding: .5rem .65rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        textarea { min-height: 160px; resize: vertical; }
        .actions { display: flex; gap: .75rem; }
        button { padding: .5rem 1rem; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #0b5ed7; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .flash { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .flash-success { background: #e6f4ea; color: #1e4620; }
        .flash-error { background: #fdecea; color: #611a15; }
        .error-list { margin: 0 0 1rem; padding-left: 1.2rem; color: #611a15; }
    </style>
</head>
<body>
    <header>
        <div>
            <h1 style="margin: 0; font-size: 1.75rem;">商品編集</h1>
            <p style="margin: .25rem 0 0; color: #555;">ID: <?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <nav>
            <a href="/admin/products">一覧へ戻る</a>
        </nav>
    </header>

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

    <form action="/admin/products/update" method="post">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($updateToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8'); ?>">

        <label>
            商品名
            <input type="text" name="name" value="<?= htmlspecialchars($input['name'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        </label>

        <label>
            価格 (円)
            <input type="text" name="price" value="<?= htmlspecialchars($input['price'], ENT_QUOTES, 'UTF-8'); ?>" inputmode="decimal" pattern="^\\d+(?:\\.\\d{1,2})?$" required>
        </label>

        <label>
            在庫数
            <input type="number" name="stock" value="<?= htmlspecialchars($input['stock'], ENT_QUOTES, 'UTF-8'); ?>" min="0" step="1" required>
        </label>

        <label>
            公開状態
            <select name="is_active">
                <option value="1" <?= $input['is_active'] === '1' ? 'selected' : ''; ?>>公開</option>
                <option value="0" <?= $input['is_active'] === '0' ? 'selected' : ''; ?>>非公開</option>
            </select>
        </label>

        <label>
            商品説明
            <textarea name="description" maxlength="2000"><?= htmlspecialchars($input['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>

        <div class="actions">
            <button type="submit" class="btn-primary">更新する</button>
        </div>
    </form>

    <form action="/admin/products/toggle" method="post" style="margin-top: 2rem;">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($toggleToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="btn-secondary">
            <?= $product->isActive() ? '非公開にする' : '公開する'; ?>
        </button>
    </form>
</body>
</html>
