<?php
/** @var App\Model\User[] $users */
/** @var array<int,array{type:string,message:string}> $flashes */
/** @var string|null $status */

$currentStatus = $status ?? 'all';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー管理</title>
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
        .status--deleted { color: #555; font-style: italic; }
        .status--admin { color: #0b5ed7; font-weight: 600; }
        .filters { display: flex; gap: .75rem; margin-bottom: 1rem; }
        .filters a { padding: .35rem .65rem; border-radius: 999px; border: 1px solid #0b5ed7; }
        .filters a[aria-current="true"] { background: #0b5ed7; color: #fff; }
        .flash { padding: .75rem 1rem; border-radius: 6px; margin-bottom: .5rem; }
        .flash-success { background: #e6f4ea; color: #1e4620; }
        .flash-error { background: #fdecea; color: #611a15; }
    </style>
</head>
<body>
    <header>
        <div>
            <h1 style="margin: 0; font-size: 1.75rem;">ユーザー一覧</h1>
            <p style="margin: .25rem 0 0; color: #555;">権限と削除状態を確認・編集できます。</p>
        </div>
        <nav>
            <a href="/admin">ダッシュボードへ戻る</a>
        </nav>
    </header>

    <?php if ($flashes !== []): ?>
        <?php foreach ($flashes as $flash): ?>
            <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="filters">
        <a href="/admin/users" aria-current="<?= $currentStatus === 'all' ? 'true' : 'false'; ?>">すべて</a>
        <a href="/admin/users?status=active" aria-current="<?= $currentStatus === 'active' ? 'true' : 'false'; ?>">有効ユーザー</a>
        <a href="/admin/users?status=deleted" aria-current="<?= $currentStatus === 'deleted' ? 'true' : 'false'; ?>">削除済</a>
    </div>

    <?php if ($users === []): ?>
        <p>条件に一致するユーザーは見つかりませんでした。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ユーザー名</th>
                    <th>メールアドレス</th>
                    <th>権限</th>
                    <th>状態</th>
                    <th>住所</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $user->getId(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="status<?= $user->isAdmin() ? ' status--admin' : ''; ?>">
                                <?= $user->isAdmin() ? '管理者' : '一般'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user->isDeleted()): ?>
                                <span class="status status--deleted">削除済<?= $user->getDeletedAt() ? ' / ' . htmlspecialchars($user->getDeletedAt()->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') : ''; ?></span>
                            <?php else: ?>
                                <span class="status">有効</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($user->getAddress(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><a href="/admin/users/edit?id=<?= htmlspecialchars((string) $user->getId(), ENT_QUOTES, 'UTF-8'); ?>">編集</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
