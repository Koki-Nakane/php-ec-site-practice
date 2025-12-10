<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\UserMapper;
use App\Model\User;
use App\Service\CsrfTokenManager;
use App\Service\PasswordValidator;
use App\Service\TemplateRenderer;
use DateTimeImmutable;

final class AuthController
{
    public function __construct(
        private UserMapper $userMapper,
        private CsrfTokenManager $csrfTokens,
        private PasswordValidator $passwordValidator,
        private TemplateRenderer $views,
    ) {
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

        $html = $this->views->render('auth/login.php', [
            'msgs' => (array) $msgs,
            'redirect' => $redirect,
            'csrfToken' => $csrfToken,
        ]);

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

        $html = $this->views->render('auth/register.php', [
            'msgs' => (array) $msgs,
            'old' => $old,
            'passwordPolicy' => $passwordPolicy,
            'csrfToken' => $csrfToken,
        ]);

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
