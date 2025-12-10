<?php
/** @var array $msgs */
/** @var string $redirect */
/** @var string $csrfToken */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 420px; line-height: 1.6; }
        form { border: 1px solid #ddd; padding: 1.25rem; border-radius: 8px; background: #fff; }
        label { display: block; margin-bottom: .75rem; }
        input { width: 100%; padding: .6rem; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: .75rem; border: none; border-radius: 4px; background: #0d47a1; color: #fff; font-size: 1rem; cursor: pointer; }
        button:hover { background: #0b3c8c; }
        .flash { margin-bottom: .75rem; padding: .75rem; border-radius: 4px; background: #fbe9e7; color: #c62828; }
        .link { margin-top: 1rem; text-align: center; }
    </style>
</head>
<body>
    <h1>ログイン</h1>
    <?php foreach ((array) $msgs as $m): ?>
        <div class="flash">※ <?php echo htmlspecialchars((string) $m, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
    <form action="/login" method="post">
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit">ログイン</button>
    </form>
    <div class="link"><a href="/password/forgot">パスワードをお忘れの方はこちら</a></div>
</body>
</html>
