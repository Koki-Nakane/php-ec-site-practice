<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\UserMapper;
use App\Service\CsrfTokenManager;
use App\Service\PasswordResetService;
use App\Service\PasswordValidator;
use App\Service\TemplateRenderer;

final class PasswordResetController
{
    public function __construct(
        private UserMapper $users,
        private CsrfTokenManager $csrfTokens,
        private PasswordResetService $passwordResets,
        private PasswordValidator $passwordValidator,
        private TemplateRenderer $views,
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
        $html = $this->views->render('auth/password_request_sent.php');

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
        $html = $this->views->render('auth/password_reset_complete.php');

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function renderRequestForm(string $email, array $errors, int $status = 200): Response
    {
        $csrfToken = $this->csrfTokens->issue('web');

        $html = $this->views->render('auth/password_request.php', [
            'csrfToken' => $csrfToken,
            'email' => $email,
            'errors' => $errors,
        ]);

        return new Response($status, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function renderResetForm(string $token, array $errors, int $status): Response
    {
        $csrfToken = $this->csrfTokens->issue('web');

        $html = $this->views->render('auth/password_reset.php', [
            'csrfToken' => $csrfToken,
            'token' => $token,
            'errors' => $errors,
            'passwordPolicy' => $this->passwordValidator->getPolicyDescription(),
        ]);

        return new Response($status, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function renderInvalidToken(): Response
    {
        $html = $this->views->render('auth/password_invalid_token.php');

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
