<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\UserMapper;
use App\Service\CsrfTokenManager;
use App\Service\PasswordResetService;
use App\Service\PasswordValidator;

final class PasswordResetController
{
    public function __construct(
        private UserMapper $users,
        private CsrfTokenManager $csrfTokens,
        private PasswordResetService $passwordResets,
        private PasswordValidator $passwordValidator,
    ) {
    }

    public function showRequestForm(Request $request): Response
    {
        return $this->renderRequestForm('', []);
    }

    public function handleRequest(Request $request): Response
    {
        $email = trim((string) ($request->body['email'] ?? ''));
        $errors = [];

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = '有効なメールアドレスを入力してください。';
        }

        $user = null;
        if ($errors === []) {
            $user = $this->users->findByEmail($email);
            if ($user === null) {
                $errors[] = '入力されたメールアドレスは登録されていません。';
            }
        }

        if ($errors !== []) {
            return $this->renderRequestForm($email, $errors, 422);
        }

        $resetBaseUrl = $this->buildResetBaseUrl();
        $this->passwordResets->request($user, $resetBaseUrl);

        return Response::redirect('/password/forgot/sent', 303);
    }

    public function showRequestSent(Request $request): Response
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <title>パスワード再設定メールを送信しました</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <h1>パスワード再設定メールを送信しました</h1>
            <p>入力いただいたメールアドレス宛に、再設定用リンクを送信しました。メールに記載された手順に従ってください。</p>
            <p><a href="/login">ログイン画面に戻る</a></p>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function showResetForm(Request $request): Response
    {
        $token = (string) ($request->query['token'] ?? '');
        if ($token === '') {
            return $this->renderInvalidToken();
        }

        $record = $this->passwordResets->findValidToken($token);
        if ($record === null) {
            return $this->renderInvalidToken();
        }

        return $this->renderResetForm($token, [], 200);
    }

    public function handleReset(Request $request): Response
    {
        $token = (string) ($request->body['token'] ?? '');
        $record = $this->passwordResets->findValidToken($token);
        if ($record === null) {
            return $this->renderInvalidToken();
        }

        $password = (string) ($request->body['password'] ?? '');
        $confirmation = (string) ($request->body['password_confirmation'] ?? '');

        $errors = $this->passwordValidator->validate($password, $confirmation);
        if ($errors !== []) {
            return $this->renderResetForm($token, $errors, 422);
        }

        $user = $this->users->find($record['user_id']);
        if ($user === null) {
            return $this->renderInvalidToken();
        }

        $user->resetPassword($password);
        $this->users->save($user);
        $this->passwordResets->markAsUsed($record['id']);

        return Response::redirect('/password/reset/complete', 303);
    }

    public function showResetComplete(Request $request): Response
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <title>パスワードを変更しました</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <h1>パスワードを変更しました</h1>
            <p>新しいパスワードでログインできます。</p>
            <p><a href="/login">ログイン画面へ</a></p>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function renderRequestForm(string $email, array $errors, int $status = 200): Response
    {
        $csrfToken = $this->csrfTokens->issue('web');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <title>パスワード再設定</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <h1>パスワード再設定</h1>
            <p>登録済みのメールアドレスを入力すると、再設定用リンクを送信します。</p>
            <?php if ($errors !== []): ?>
                <div style="color:#b00;">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="/password/forgot">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>
                    メールアドレス
                    <input type="email" name="email" required value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <div style="margin-top:1rem;">
                    <button type="submit">再設定リンクを送信</button>
                </div>
            </form>
            <p style="margin-top:1rem;"><a href="/login">ログイン画面に戻る</a></p>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response($status, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function renderResetForm(string $token, array $errors, int $status): Response
    {
        $csrfToken = $this->csrfTokens->issue('web');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <title>新しいパスワードを設定</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <h1>新しいパスワードを設定</h1>
            <?php if ($errors !== []): ?>
                <div style="color:#b00;">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" action="/password/reset">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <label>
                    新しいパスワード
                    <input type="password" name="password" required minlength="8">
                </label>
                <label>
                    新しいパスワード（確認用）
                    <input type="password" name="password_confirmation" required minlength="8">
                </label>
                <p>英大文字・英小文字・数字・記号のうち2種類以上を含めてください。</p>
                <div style="margin-top:1rem;">
                    <button type="submit">パスワードを変更する</button>
                </div>
            </form>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response($status, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function renderInvalidToken(): Response
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <title>リンクが無効です</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <h1>リンクが無効か期限切れです</h1>
            <p>お手数ですが、もう一度パスワード再設定を申請してください。</p>
            <p><a href="/password/forgot">再設定リンクを申請する</a></p>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
        return new Response(410, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function buildResetBaseUrl(): string
    {
        $scheme = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return sprintf('%s://%s/password/reset', $scheme, $host);
    }
}
