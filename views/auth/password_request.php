<?php
/** @var string $csrfToken */
/** @var string $email */
/** @var array $errors */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワード再設定</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 480px; line-height: 1.6; }
        form { border: 1px solid #ddd; padding: 1.25rem; border-radius: 8px; background: #fff; }
        label { display: block; margin-bottom: .75rem; }
        input { width: 100%; padding: .6rem; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: .75rem; border: none; border-radius: 4px; background: #0d47a1; color: #fff; font-size: 1rem; cursor: pointer; }
        button:hover { background: #0b3c8c; }
        .errors { margin: 0 0 1rem; padding: .75rem; background: #fdecea; color: #b71c1c; border-radius: 4px; }
        .link { margin-top: 1rem; text-align: center; }
    </style>
</head>
<body>
    <h1>パスワード再設定</h1>
    <p>登録済みのメールアドレスを入力すると、再設定用リンクを送信します。</p>
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" action="/password/forgot">
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <label>
            メールアドレス
            <input type="email" name="email" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <button type="submit">再設定リンクを送信</button>
    </form>
    <div class="link"><a href="/login">ログイン画面に戻る</a></div>
</body>
</html>
