<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\UserMapper;

final class AuthController
{
    private UserMapper $userMapper;

    public function __construct(
        UserMapper $userMapper
    ) {
        $this->userMapper = $userMapper;
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->userMapper->findByEmail($email);

        if ($user === null) {
            return false;
        }

        if (password_verify($password, $user->getHashedPassword())) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->getId();
            return true;
        }

        return false;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    // GET /login
    public function showLogin(Request $request): Response
    {
        // Helper: read & clear flash
        $msgs = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $redirect = $this->sanitizeRedirect($request->query['redirect'] ?? '/');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>ログイン</title>
            <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
        </head>
        <body>
            <h1>ログイン</h1>
            <?php foreach ((array)$msgs as $m): ?>
                <p style="color:red;">※ <?php echo htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endforeach; ?>
            <form action="/login" method="post">
                <label>Email: <input type="email" name="email" required></label><br>
                <label>Password: <input type="password" name="password" required></label><br>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">ログイン</button>
            </form>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    // POST /login
    public function handleLogin(Request $request): Response
    {
        $email    = (string)($request->body['email'] ?? '');
        $password = (string)($request->body['password'] ?? '');
        $redirect = $this->sanitizeRedirect($request->body['redirect'] ?? '/');

        if ($this->login($email, $password)) {
            return Response::redirect($redirect, 303);
        }

        $_SESSION['flash'][] = 'メールアドレスまたはパスワードが間違っています。';
        return Response::redirect('/login?redirect=' . urlencode($redirect), 303);
    }

    private function sanitizeRedirect(?string $raw): string
    {
        $raw = $raw ?? '/';
        if ($raw === '' || $raw[0] !== '/') {
            return '/';
        }
        if (str_starts_with($raw, '//') || preg_match('#^[a-zA-Z]+://#', $raw)) {
            return '/';
        }
        return $raw;
    }
}
