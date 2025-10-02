<?php

declare(strict_types=1);

use App\Controller\AuthController;

require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// Helper: HTML escape
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Helper: read & clear flash messages
function take_flashes(): array
{
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($msgs) ? $msgs : [];
}

// Validate redirect target (allow only same-site absolute path)
function sanitize_redirect(?string $raw): string
{
    $raw = $raw ?? '/';
    if ($raw === '' || $raw[0] !== '/') {
        return '/';
    }
    // Disallow protocol-relative and external
    if (str_starts_with($raw, '//') || preg_match('#^[a-zA-Z]+://#', $raw)) {
        return '/';
    }
    return $raw;
}

$redirect = sanitize_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string)($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    try {
        /** @var App\Infrastructure\Container $container */
        $container = require __DIR__ . '/../config/container.php';
        /** @var AuthController $auth */
        $auth = $container->get(AuthController::class);

        if ($auth->login($email, $password)) {
            header('Location: ' . $redirect, true, 303);
            exit;
        }

        $_SESSION['flash'][] = 'メールアドレスまたはパスワードが違います。';
        header('Location: /login.php?redirect=' . urlencode($redirect), true, 303);
        exit;
    } catch (Throwable $e) {
        // 最低限の例外対応（本来は共通エラーハンドラで処理）
        $_SESSION['flash'][] = '内部エラーが発生しました。しばらくしてからお試しください。';
        header('Location: /login.php?redirect=' . urlencode($redirect), true, 303);
        exit;
    }
}

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ログイン</title>
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
  <style>
    body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif; margin: 2rem; }
    .flash { background: #fee; color: #b00; border: 1px solid #fbb; padding: .75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
    form { max-width: 400px; display: grid; gap: .75rem; }
    label { display: grid; gap: .25rem; }
    input[type="email"], input[type="password"] { padding: .5rem .6rem; border: 1px solid #ccc; border-radius: 4px; }
    button { padding: .6rem 1rem; border: 0; background: #0b5ed7; color: #fff; border-radius: 4px; cursor: pointer; }
    button:hover { background: #0a53be; }
  </style>
</head>
<body>
  <h1>ログイン</h1>

  <?php foreach ($flashes as $msg): ?>
    <div class="flash"><?php echo h((string)$msg); ?></div>
  <?php endforeach; ?>

  <form method="post" action="/login.php">
    <input type="hidden" name="redirect" value="<?php echo h($redirect); ?>">

    <label>
      メールアドレス
      <input type="email" name="email" required autocomplete="email">
    </label>

    <label>
      パスワード
      <input type="password" name="password" required autocomplete="current-password">
    </label>

    <button type="submit">ログイン</button>
  </form>
</body>
</html>
