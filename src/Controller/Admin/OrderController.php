<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\OrderMapper;
use App\Model\Enum\OrderStatus;
use App\Service\CsrfTokenManager;
use App\Service\TemplateRenderer;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class OrderController
{
    public function __construct(
        private OrderMapper $orders,
        private TemplateRenderer $views,
        private CsrfTokenManager $csrfTokens,
    ) {
    }

    public function index(Request $request): Response
    {
        $statusParam = isset($request->query['status']) ? (string) $request->query['status'] : 'all';
        $deletedParam = isset($request->query['deleted']) ? (string) $request->query['deleted'] : 'active';

        $status = null;
        if ($statusParam !== 'all' && $statusParam !== '') {
            $candidate = filter_var($statusParam, FILTER_VALIDATE_INT);
            if ($candidate !== false && OrderStatus::isValid((int) $candidate)) {
                $status = (int) $candidate;
            } else {
                $statusParam = 'all';
            }
        } else {
            $statusParam = 'all';
        }

        $onlyDeleted = null;
        if ($deletedParam === 'deleted') {
            $onlyDeleted = true;
        } elseif ($deletedParam === 'active') {
            $onlyDeleted = false;
        } else {
            $deletedParam = 'all';
        }

        $orders = $this->orders->listForAdmin($status, $onlyDeleted, 100, 0);

        $html = $this->views->render('admin/orders/index.php', [
            'orders' => $orders,
            'statuses' => OrderStatus::labels(),
            'selectedStatus' => $statusParam,
            'deletedFilter' => $deletedParam,
            'flashes' => $this->takeFlashes(),
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function edit(Request $request): Response
    {
        $id = $this->requireOrderId($request->query['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正な注文IDが指定されました。');
            return Response::redirect('/admin/orders', 303);
        }

        $order = $this->orders->findForAdmin($id);
        if ($order === null) {
            $this->flash('error', '指定された注文が見つかりません。');
            return Response::redirect('/admin/orders', 303);
        }

        $form = $this->takeFormState($id);

        $html = $this->views->render('admin/orders/edit.php', [
            'order' => $order,
            'statuses' => OrderStatus::labels(),
            'flashes' => $this->takeFlashes(),
            'form' => $form,
            'updateToken' => $this->csrfTokens->issue($this->tokenId('update', $id)),
            'toggleDeletionToken' => $this->csrfTokens->issue($this->tokenId('toggle_deletion', $id)),
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function update(Request $request): Response
    {
        $id = $this->requireOrderId($request->body['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正なリクエストです。');
            return Response::redirect('/admin/orders', 303);
        }

        if (!$this->csrfTokens->validate($this->tokenId('update', $id), $request->body['_token'] ?? null)) {
            $this->flash('error', 'フォームの有効期限が切れました。もう一度送信してください。');
            return Response::redirect('/admin/orders/edit?id=' . $id, 303);
        }

        $input = $this->sanitizeInput($request->body);
        [$errors, $normalized] = $this->validateInput($input);
        if ($errors !== []) {
            $this->rememberFormState($id, $input, $errors);
            return Response::redirect('/admin/orders/edit?id=' . $id, 303);
        }

        $order = $this->orders->findForAdmin($id);
        if ($order === null) {
            $this->flash('error', '指定された注文が見つかりません。');
            return Response::redirect('/admin/orders', 303);
        }

        try {
            $order->changeShippingAddress($normalized['shipping_address']);
            $order->setStatus($normalized['status']);
            $this->orders->save($order);
        } catch (InvalidArgumentException|DomainException $e) {
            $this->flash('error', $e->getMessage());
            $this->rememberFormState($id, $input, [$e->getMessage()]);
            return Response::redirect('/admin/orders/edit?id=' . $id, 303);
        }

        $this->flash('success', '注文情報を更新しました。');

        return Response::redirect('/admin/orders/edit?id=' . $id, 303);
    }

    public function toggleDeletion(Request $request): Response
    {
        $id = $this->requireOrderId($request->body['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正なリクエストです。');
            return Response::redirect('/admin/orders', 303);
        }

        if (!$this->csrfTokens->validate($this->tokenId('toggle_deletion', $id), $request->body['_token'] ?? null)) {
            $this->flash('error', 'フォームの有効期限が切れました。もう一度送信してください。');
            return Response::redirect('/admin/orders/edit?id=' . $id, 303);
        }

        $now = new DateTimeImmutable();

        $order = $this->orders->findForAdmin($id);
        if ($order === null) {
            $this->flash('error', '指定された注文が見つかりません。');
            return Response::redirect('/admin/orders', 303);
        }

        try {
            if ($order->isDeleted()) {
                $this->orders->restore($id, $now);
                $this->flash('success', '注文を復元しました。');
            } else {
                $this->orders->markDeleted($id, $now);
                $this->flash('success', '注文を削除しました。');
            }
        } catch (DomainException|InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }

        return Response::redirect('/admin/orders/edit?id=' . $id, 303);
    }

    private function sanitizeInput(array $body): array
    {
        return [
            'shipping_address' => isset($body['shipping_address']) ? trim((string) $body['shipping_address']) : '',
            'status' => isset($body['status']) ? trim((string) $body['status']) : '',
        ];
    }

    /**
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    private function validateInput(array $input): array
    {
        $errors = [];
        $normalized = [];

        if (mb_strlen($input['shipping_address'], 'UTF-8') > 1000) {
            $errors[] = '配送先住所は1000文字以内で入力してください。';
        } else {
            $normalized['shipping_address'] = $input['shipping_address'];
        }

        $status = filter_var($input['status'], FILTER_VALIDATE_INT);
        if ($status === false || !OrderStatus::isValid((int) $status)) {
            $errors[] = 'ステータスの選択が不正です。';
        } else {
            $normalized['status'] = (int) $status;
        }

        return [$errors, $normalized];
    }

    private function requireOrderId(mixed $raw): ?int
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    private function flash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['admin_order_flash'][] = ['type' => $type, 'message' => $message];
    }

    private function takeFlashes(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $flashes = $_SESSION['admin_order_flash'] ?? [];
        unset($_SESSION['admin_order_flash']);

        return $flashes;
    }

    private function rememberFormState(int $orderId, array $input, array $errors): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['admin_order_form'][$orderId] = [
            'input' => $input,
            'errors' => $errors,
        ];
    }

    private function takeFormState(int $orderId): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $form = $_SESSION['admin_order_form'][$orderId] ?? null;
        unset($_SESSION['admin_order_form'][$orderId]);

        return $form;
    }

    private function tokenId(string $action, int $orderId): string
    {
        return sprintf('admin_order_%s_%d', $action, $orderId);
    }
}
