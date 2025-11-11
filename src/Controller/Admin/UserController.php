<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\UserMapper;
use App\Model\User;
use App\Service\CsrfTokenManager;
use App\Service\TemplateRenderer;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDOException;

final class UserController
{
    public function __construct(
        private UserMapper $users,
        private TemplateRenderer $views,
        private CsrfTokenManager $csrfTokens
    ) {
    }

    public function index(Request $request): Response
    {
        $status = isset($request->query['status']) ? (string) $request->query['status'] : null;
        $onlyDeleted = null;

        if ($status === 'deleted') {
            $onlyDeleted = true;
        } elseif ($status === 'active') {
            $onlyDeleted = false;
        }

        $items = $this->users->listForAdmin($onlyDeleted, 100, 0);

        $html = $this->views->render('admin/users/index.php', [
            'users' => $items,
            'flashes' => $this->takeFlashes(),
            'status' => $status,
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function edit(Request $request): Response
    {
        $id = $this->requireUserId($request->query['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正なユーザーIDが指定されました。');
            return Response::redirect('/admin/users', 303);
        }

        $user = $this->users->find($id);
        if ($user === null) {
            $this->flash('error', '指定されたユーザーが見つかりません。');
            return Response::redirect('/admin/users', 303);
        }

        $form = $this->takeFormState($id);

        $html = $this->views->render('admin/users/edit.php', [
            'user' => $user,
            'flashes' => $this->takeFlashes(),
            'form' => $form,
            'updateToken' => $this->csrfTokens->issue($this->tokenId('update', $id)),
            'toggleAdminToken' => $this->csrfTokens->issue($this->tokenId('toggle_admin', $id)),
            'toggleDeletionToken' => $this->csrfTokens->issue($this->tokenId('toggle_deletion', $id)),
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function update(Request $request): Response
    {
        $id = $this->requireUserId($request->body['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正なリクエストです。');
            return Response::redirect('/admin/users', 303);
        }

        if (!$this->csrfTokens->validate($this->tokenId('update', $id), $request->body['_token'] ?? null)) {
            $this->flash('error', 'フォームの有効期限が切れました。もう一度送信してください。');
            return Response::redirect('/admin/users/edit?id=' . $id, 303);
        }

        $input = $this->sanitizeInput($request->body);
        [$errors, $normalized] = $this->validateInput($input);
        if ($errors !== []) {
            $this->rememberFormState($id, $input, $errors);
            return Response::redirect('/admin/users/edit?id=' . $id, 303);
        }

        $user = $this->users->find($id);
        if ($user === null) {
            $this->flash('error', '指定されたユーザーが見つかりません。');
            return Response::redirect('/admin/users', 303);
        }

        try {
            $this->applyChanges($user, $normalized);
            $this->users->save($user);
        } catch (InvalidArgumentException|DomainException $e) {
            $this->flash('error', $e->getMessage());
            $this->rememberFormState($id, $input, [$e->getMessage()]);
            return Response::redirect('/admin/users/edit?id=' . $id, 303);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $message = '指定されたメールアドレスは既に使用されています。';
            } else {
                $message = '更新処理中にエラーが発生しました。';
            }

            $this->flash('error', $message);
            $this->rememberFormState($id, $input, [$message]);
            return Response::redirect('/admin/users/edit?id=' . $id, 303);
        }

        $this->flash('success', 'ユーザー情報を更新しました。');

        return Response::redirect('/admin/users/edit?id=' . $id, 303);
    }

    public function toggleAdmin(Request $request): Response
    {
        $id = $this->requireUserId($request->body['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正なリクエストです。');
            return Response::redirect('/admin/users', 303);
        }

        if (!$this->csrfTokens->validate($this->tokenId('toggle_admin', $id), $request->body['_token'] ?? null)) {
            $this->flash('error', 'フォームの有効期限が切れました。もう一度送信してください。');
            return Response::redirect('/admin/users/edit?id=' . $id, 303);
        }

        $user = $this->users->find($id);
        if ($user === null) {
            $this->flash('error', '指定されたユーザーが見つかりません。');
            return Response::redirect('/admin/users', 303);
        }

        if ($user->isAdmin()) {
            $user->demoteFromAdmin();
            $message = 'ユーザーを一般権限に変更しました。';
        } else {
            $user->promoteToAdmin();
            $message = 'ユーザーを管理者権限に変更しました。';
        }

        try {
            $this->users->save($user);
            $this->flash('success', $message);
        } catch (InvalidArgumentException|DomainException|PDOException $e) {
            $this->flash('error', '権限の更新に失敗しました。');
        }

        return Response::redirect('/admin/users/edit?id=' . $id, 303);
    }

    public function toggleDeletion(Request $request): Response
    {
        $id = $this->requireUserId($request->body['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正なリクエストです。');
            return Response::redirect('/admin/users', 303);
        }

        if (!$this->csrfTokens->validate($this->tokenId('toggle_deletion', $id), $request->body['_token'] ?? null)) {
            $this->flash('error', 'フォームの有効期限が切れました。もう一度送信してください。');
            return Response::redirect('/admin/users/edit?id=' . $id, 303);
        }

        $user = $this->users->find($id);
        if ($user === null) {
            $this->flash('error', '指定されたユーザーが見つかりません。');
            return Response::redirect('/admin/users', 303);
        }

        try {
            if ($user->isDeleted()) {
                $this->users->restore($id);
                $this->flash('success', 'ユーザーを復元しました。');
            } else {
                $this->users->markDeleted($id, new DateTimeImmutable());
                $this->flash('success', 'ユーザーを削除しました。');
            }
        } catch (DomainException|InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (PDOException $e) {
            $this->flash('error', '削除状態の更新に失敗しました。');
        }

        return Response::redirect('/admin/users/edit?id=' . $id, 303);
    }

    private function sanitizeInput(array $body): array
    {
        return [
            'name' => isset($body['name']) ? trim((string) $body['name']) : '',
            'email' => isset($body['email']) ? trim((string) $body['email']) : '',
            'address' => isset($body['address']) ? trim((string) $body['address']) : '',
            'is_admin' => $body['is_admin'] ?? null,
        ];
    }

    /**
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    private function validateInput(array $input): array
    {
        $errors = [];
        $normalized = [];

        if ($input['name'] === '') {
            $errors[] = 'ユーザー名は必須です。';
        } elseif (strlen($input['name']) > 255) {
            $errors[] = 'ユーザー名は255文字以内で入力してください。';
        } elseif (preg_match('/^[A-Za-z0-9_]+$/', $input['name']) !== 1) {
            $errors[] = 'ユーザー名は半角英数字とアンダースコアのみ使用できます。';
        } else {
            $normalized['name'] = $input['name'];
        }

        if ($input['email'] === '' || filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = '有効なメールアドレスを入力してください。';
        } elseif (strlen($input['email']) > 255) {
            $errors[] = 'メールアドレスは255文字以内で入力してください。';
        } else {
            $normalized['email'] = $input['email'];
        }

        if (mb_strlen($input['address'], 'UTF-8') > 500) {
            $errors[] = '住所は500文字以内で入力してください。';
        } else {
            $normalized['address'] = $input['address'];
        }

        $isAdmin = filter_var($input['is_admin'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($isAdmin === null) {
            $errors[] = '権限の選択が不正です。';
        } else {
            $normalized['is_admin'] = $isAdmin;
        }

        return [$errors, $normalized];
    }

    private function applyChanges(User $user, array $normalized): void
    {
        $user->rename($normalized['name']);
        $user->changeEmail($normalized['email']);
        $user->changeAddress($normalized['address']);

        if ($normalized['is_admin'] === true) {
            $user->promoteToAdmin();
        } else {
            $user->demoteFromAdmin();
        }
    }

    private function requireUserId(mixed $raw): ?int
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    private function flash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['admin_user_flash'][] = ['type' => $type, 'message' => $message];
    }

    private function takeFlashes(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $flashes = $_SESSION['admin_user_flash'] ?? [];
        unset($_SESSION['admin_user_flash']);

        return $flashes;
    }

    private function rememberFormState(int $userId, array $input, array $errors): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['admin_user_form'][$userId] = [
            'input' => $input,
            'errors' => $errors,
        ];
    }

    private function takeFormState(int $userId): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $form = $_SESSION['admin_user_form'][$userId] ?? null;
        unset($_SESSION['admin_user_form'][$userId]);

        return $form;
    }

    private function tokenId(string $action, int $userId): string
    {
        return sprintf('admin_user_%s_%d', $action, $userId);
    }
}
