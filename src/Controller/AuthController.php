<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\UserMapper;
use App\Model\User;
use App\Service\CsrfTokenManager;
use App\Service\PasswordValidator;
use DateTimeImmutable;

final class AuthController
{
    private UserMapper $userMapper;
    private CsrfTokenManager $csrfTokens;
    private PasswordValidator $passwordValidator;

    public function __construct(
        UserMapper $userMapper,
        CsrfTokenManager $csrfTokens,
        PasswordValidator $passwordValidator,
    ) {
        $this->userMapper = $userMapper;
        $this->csrfTokens = $csrfTokens;
        $this->passwordValidator = $passwordValidator;
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->userMapper->findByEmail($email);

        if ($user === null) {
            return false;
        }

        if ($user->isDeleted()) {
            return false;
        }

        if (!password_verify($password, $user->getHashedPassword())) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->getId();

        try {
            $userId = $user->getId();
            if ($userId !== null) {
                $this->userMapper->updateLastLogin($userId, new DateTimeImmutable());
            }
        } catch (\Throwable $e) {
            error_log('Failed to update last_login_at: ' . $e->getMessage());
        }

        return true;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function requireUser(): ?User
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return null;
        }

        $user = $this->userMapper->find((int) $userId);
        if ($user === null || $user->isDeleted()) {
            return null;
        }

        return $user;
    }

    public function isAdmin(): bool
    {
        $user = $this->requireUser();
        if ($user === null) {
            return false;
        }

        return $user->isAdmin();
    }

    // GET /login
    public function showLogin(Request $request): Response
    {
        // Helper: read & clear flash
        $msgs = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $redirect = $this->sanitizeRedirect($request->query['redirect'] ?? '/');
        $csrfToken = $this->csrfTokens->issue('login_form');

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
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Email: <input type="email" name="email" required></label><br>
                <label>Password: <input type="password" name="password" required></label><br>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">ログイン</button>
            </form>
            <p><a href="/password/forgot">パスワードをお忘れの方はこちら</a></p>
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

    // GET /register
    public function showRegister(Request $request): Response
    {
        $msgs = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $old = $this->pullRegisterInput();
        $passwordPolicy = $this->passwordValidator->getPolicyDescription();
        $csrfToken = $this->csrfTokens->issue('register_form');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>ユーザー登録</title>
            <style>
                body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 480px; line-height: 1.6; }
                form { border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; background: #fff; }
                label { display: block; margin-bottom: .75rem; }
                input, textarea { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
                button { width: 100%; padding: .75rem; border: none; border-radius: 4px; background: #0d47a1; color: #fff; font-size: 1rem; }
                .flash { margin-bottom: .75rem; padding: .75rem; border-radius: 4px; background: #fbe9e7; color: #c62828; }
                .link { margin-top: 1rem; text-align: center; }
                .hint { margin-top: .25rem; font-size: .9rem; color: #555; }
            </style>
        </head>
        <body>
            <h1>ユーザー登録</h1>
            <?php foreach ((array)$msgs as $message): ?>
                <div class="flash">※ <?php echo htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
            <form action="/register" method="post">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>
                    ユーザー名 (半角英数字とアンダースコア)
                    <input type="text" name="name" value="<?php echo htmlspecialchars($old['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>
                <label>
                    メールアドレス
                    <input type="email" name="email" value="<?php echo htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>
                <label>
                    郵便番号 (ハイフンなし7桁)
                    <div style="display:flex; gap:.5rem; align-items:center;">
                        <input type="text" name="postal_code" inputmode="numeric" pattern="\d{7}" maxlength="7" value="<?php echo htmlspecialchars($old['postal_code'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <button type="button" id="postal-code-lookup" style="flex-shrink:0; padding:.5rem 1rem;">郵便番号から住所を自動入力</button>
                    </div>
                    <p id="lookup-message" class="hint"></p>
                </label>
                <label>
                    都道府県
                    <input type="text" name="prefecture" value="<?php echo htmlspecialchars($old['prefecture'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>
                <label>
                    市区町村
                    <input type="text" name="city" value="<?php echo htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>
                <label>
                    それ以降の住所
                    <input type="text" name="street" value="<?php echo htmlspecialchars($old['street'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>
                <label>
                    パスワード (8文字以上)
                    <input type="password" name="password" required>
                    <small style="display:block; color:#555; margin-top:.25rem;"><?php echo htmlspecialchars($passwordPolicy, ENT_QUOTES, 'UTF-8'); ?></small>
                </label>
                <label>
                    パスワード（確認）
                    <input type="password" name="password_confirmation" required>
                </label>
                <button type="submit">登録する</button>
            </form>
            <div class="link"><a href="/login">ログインはこちら</a></div>
            <script>
            (() => {
                const button = document.getElementById('postal-code-lookup');
                const postalInput = document.querySelector('input[name="postal_code"]');
                const prefInput = document.querySelector('input[name="prefecture"]');
                const cityInput = document.querySelector('input[name="city"]');
                const streetInput = document.querySelector('input[name="street"]');
                const messageEl = document.getElementById('lookup-message');

                const setMessage = (text, isError) => {
                    if (!messageEl) return;
                    messageEl.textContent = text;
                    messageEl.style.color = isError ? '#c62828' : '#1b5e20';
                };

                if (!button || !postalInput) {
                    return;
                }

                button.addEventListener('click', async () => {
                    const postal = (postalInput.value || '').replace(/\D/g, '');
                    if (postal.length !== 7) {
                        setMessage('郵便番号はハイフンなしの7桁で入力してください。', true);
                        return;
                    }

                    setMessage('検索中です...', false);

                    try {
                        const response = await fetch(`/api/postal-code?postal_code=${encodeURIComponent(postal)}`, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        const data = await response.json();

                        if (!response.ok || (data && data.error)) {
                            const message = typeof data?.error === 'string' ? data.error : '住所の取得に失敗しました。';
                            throw new Error(message);
                        }

                        if (prefInput) {
                            prefInput.value = data.prefecture ?? '';
                        }
                        if (cityInput) {
                            cityInput.value = data.city ?? '';
                        }
                        if (streetInput) {
                            streetInput.value = data.town ?? '';
                        }

                        setMessage('住所を補完しました。', false);
                    } catch (error) {
                        const message = error instanceof Error && error.message !== ''
                            ? error.message
                            : '住所の取得に失敗しました。';
                        setMessage(message, true);
                    }
                });
            })();
            </script>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    // POST /register
    public function handleRegister(Request $request): Response
    {
        $name = trim((string) ($request->body['name'] ?? ''));
        $email = trim((string) ($request->body['email'] ?? ''));
        $postalCode = preg_replace('/\D/', '', (string) ($request->body['postal_code'] ?? ''));
        $prefecture = trim((string) ($request->body['prefecture'] ?? ''));
        $city = trim((string) ($request->body['city'] ?? ''));
        $street = trim((string) ($request->body['street'] ?? ''));
        $password = (string) ($request->body['password'] ?? '');
        $passwordConfirmation = (string) ($request->body['password_confirmation'] ?? '');

        $this->rememberRegisterInput([
            'name' => $name,
            'email' => $email,
            'postal_code' => $postalCode,
            'prefecture' => $prefecture,
            'city' => $city,
            'street' => $street,
        ]);

        $csrfToken = $request->body['_token'] ?? null;
        if (!$this->csrfTokens->validate('register_form', is_string($csrfToken) ? $csrfToken : null)) {
            $this->pushFlashMessage('フォームの有効期限が切れました。もう一度やり直してください。');
            return Response::redirect('/register', 303);
        }

        $errors = [];

        if ($name === '') {
            $errors[] = 'ユーザー名を入力してください。';
        } elseif (preg_match('/^[a-zA-Z0-9_]+$/', $name) !== 1) {
            $errors[] = 'ユーザー名は半角英数字とアンダースコアのみ使用できます。';
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = '正しいメールアドレスを入力してください。';
        } elseif ($this->userMapper->findByEmail($email) !== null) {
            $errors[] = 'このメールアドレスは既に登録されています。';
        }

        $passwordErrors = $this->passwordValidator->validate($password, $passwordConfirmation);
        $errors = array_merge($errors, $passwordErrors);

        if (!preg_match('/^\d{7}$/', $postalCode)) {
            $errors[] = '郵便番号はハイフンなしの半角数字7桁で入力してください。';
        }

        if ($prefecture === '') {
            $errors[] = '都道府県を入力してください。';
        }

        if ($city === '') {
            $errors[] = '市区町村を入力してください。';
        }

        if ($street === '') {
            $errors[] = '住所の残りを入力してください。';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->pushFlashMessage($error);
            }

            return Response::redirect('/register', 303);
        }

        try {
            $user = new User(
                $name,
                $email,
                $password,
                $this->buildAddressFromParts($postalCode, $prefecture, $city, $street),
                null
            );
            $this->userMapper->save($user);
        } catch (\Throwable $e) {
            $this->pushFlashMessage('ユーザー登録に失敗しました。時間をおいて再度お試しください。');
            return Response::redirect('/register', 303);
        }

        unset($_SESSION['register_old']);
        $this->pushFlashMessage('登録が完了しました。ログインしてください。');

        return Response::redirect('/login', 303);
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

    private function pushFlashMessage(string $message): void
    {
        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }

        $_SESSION['flash'][] = $message;
    }

    /**
     * @param array{name:string,email:string,postal_code:string,prefecture:string,city:string,street:string} $input
     */
    private function rememberRegisterInput(array $input): void
    {
        $_SESSION['register_old'] = $input;
    }

    /**
     * @return array{name:string,email:string,postal_code:string,prefecture:string,city:string,street:string}
     */
    private function pullRegisterInput(): array
    {
        $defaults = [
            'name' => '',
            'email' => '',
            'postal_code' => '',
            'prefecture' => '',
            'city' => '',
            'street' => '',
        ];
        $old = $_SESSION['register_old'] ?? $defaults;
        unset($_SESSION['register_old']);

        if (!is_array($old)) {
            return $defaults;
        }

        return array_merge($defaults, array_intersect_key($old, $defaults));
    }

    private function buildAddressFromParts(string $postalCode, string $prefecture, string $city, string $street): string
    {
        $formattedPostal = sprintf('〒%s-%s', substr($postalCode, 0, 3), substr($postalCode, 3));

        return implode("\n", [
            $formattedPostal,
            $prefecture . $city,
            $street,
        ]);
    }
}
