<?php
/** @var App\Model\User $user */
/** @var array<int,array{type:string,message:string}> $flashes */
/** @var array{input:array<string,string|null>,errors:array<int,string>}|null $form */
/** @var string $updateToken */
/** @var string $toggleAdminToken */
/** @var string $toggleDeletionToken */

$input = $form['input'] ?? [
    'name' => $user->getName(),
    'email' => $user->getEmail(),
    'address' => $user->getAddress(),
    'is_admin' => $user->isAdmin() ? '1' : '0',
];
$errors = $form['errors'] ?? [];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー編集 #<?= htmlspecialchars((string) $user->getId(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 2rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        form { max-width: 640px; display: grid; gap: 1rem; }
        label { display: grid; gap: .35rem; }
        input[type="text"], input[type="email"], textarea, select { padding: .5rem .65rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        textarea { min-height: 120px; resize: vertical; }
        .actions { display: flex; gap: .75rem; }
        button { padding: .5rem 1rem; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #0b5ed7; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-danger { background: #c53030; color: #fff; }
        .flash { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .flash-success { background: #e6f4ea; color: #1e4620; }
        .flash-error { background: #fdecea; color: #611a15; }
        .error-list { margin: 0 0 1rem; padding-left: 1.2rem; color: #611a15; }
        .status-banner { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; background: #f7f7f7; display: flex; justify-content: space-between; align-items: center; }
        .status-banner strong { font-size: 1.05rem; }
    </style>
</head>
<body>
    <header>
        <div>
            <h1 style="margin: 0; font-size: 1.75rem;">ユーザー編集</h1>
            <p style="margin: .25rem 0 0; color: #555;">ID: <?= htmlspecialchars((string) $user->getId(), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <nav>
            <a href="/admin/users">一覧へ戻る</a>
        </nav>
    </header>

    <div class="status-banner">
        <div>
            <strong><?= $user->isAdmin() ? '管理者権限' : '一般権限'; ?></strong>
            <span style="margin-left: 1rem; color: <?= $user->isDeleted() ? '#b00' : '#1e4620'; ?>;">
                <?= $user->isDeleted() ? '削除済' : '有効'; ?>
                <?php if ($user->isDeleted() && $user->getDeletedAt() !== null): ?>
                    (<?= htmlspecialchars($user->getDeletedAt()->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8'); ?>)
                <?php endif; ?>
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

    <form action="/admin/users/update" method="post">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($updateToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $user->getId(), ENT_QUOTES, 'UTF-8'); ?>">

        <label>
            ユーザー名
            <input type="text" name="name" value="<?= htmlspecialchars((string) $input['name'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255" pattern="^[A-Za-z0-9_]+$">
        </label>

        <label>
            メールアドレス
            <input type="email" name="email" value="<?= htmlspecialchars((string) $input['email'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        </label>

        <label>
            権限
            <select name="is_admin" required>
                <option value="1" <?= $input['is_admin'] === '1' ? 'selected' : ''; ?>>管理者</option>
                <option value="0" <?= $input['is_admin'] === '0' ? 'selected' : ''; ?>>一般</option>
            </select>
        </label>

        <label>
            住所
            <textarea name="address" maxlength="500"><?= htmlspecialchars((string) $input['address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>

        <div class="actions">
            <button type="submit" class="btn-primary">更新する</button>
        </div>
    </form>

    <form action="/admin/users/toggle-admin" method="post" style="margin-top: 2rem;">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($toggleAdminToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $user->getId(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="btn-secondary">
            <?= $user->isAdmin() ? '一般権限に変更' : '管理者権限に変更'; ?>
        </button>
    </form>

    <form action="/admin/users/toggle-deletion" method="post" style="margin-top: 1rem;">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($toggleDeletionToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars((string) $user->getId(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="btn-danger">
            <?= $user->isDeleted() ? 'ユーザーを復元する' : 'ユーザーを削除する'; ?>
        </button>
    </form>
</body>
</html>
