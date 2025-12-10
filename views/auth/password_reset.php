<?php
/** @var string $csrfToken */
/** @var string $token */
/** @var array $errors */
/** @var string $passwordPolicy */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新しいパスワードを設定</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 520px; line-height: 1.6; }
        form { border: 1px solid #ddd; padding: 1.25rem; border-radius: 8px; background: #fff; }
        label { display: block; margin-bottom: .75rem; }
        input { width: 100%; padding: .6rem; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: .75rem; border: none; border-radius: 4px; background: #0d47a1; color: #fff; font-size: 1rem; cursor: pointer; }
        button:hover { background: #0b3c8c; }
        .errors { margin: 0 0 1rem; padding: .75rem; background: #fdecea; color: #b71c1c; border-radius: 4px; }
        .hint { color: #555; font-size: .9rem; margin-top: .25rem; }
    </style>
</head>
<body>
    <h1>新しいパスワードを設定</h1>
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" action="/password/reset">
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
        <label>
            新しいパスワード
            <input type="password" name="password" required minlength="8">
            <div class="hint"><?php echo htmlspecialchars($passwordPolicy, ENT_QUOTES, 'UTF-8'); ?></div>
        </label>
        <label>
            新しいパスワード（確認用）
            <input type="password" name="password_confirmation" required minlength="8">
        </label>
        <button type="submit">パスワードを変更する</button>
    </form>
</body>
</html>
