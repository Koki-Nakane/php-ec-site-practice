<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\OrderMapper;
use App\Mapper\ProductMapper;
use App\Mapper\UserMapper;
use App\Model\Cart;
use App\Model\Order;
use App\Service\CsrfTokenManager;
use App\Service\Exception\NoOrdersForExportException;
use App\Service\OrderCsvExporter;
use App\Service\TemplateRenderer;
use DateInterval;
use DateTimeImmutable;

final class OrderController
{
    public function __construct(
        private \PDO $pdo,
        private UserMapper $users,
        private ProductMapper $products,
        private OrderMapper $orders,
        private OrderCsvExporter $csvExporter,
        private CsrfTokenManager $csrfTokens,
        private TemplateRenderer $views,
    ) {
    }

    public function checkout(Request $request): Response
    {
        $cart = ($_SESSION['cart'] ?? null);
        if (!($cart instanceof Cart)) {
            $cart = new Cart();
        }

        if (empty($cart->getItems())) {
            $_SESSION['error_message'] = 'カートが空です。';
            return Response::redirect('/');
        }

        $userId = $_SESSION['user_id'] ?? null;
        $user = $userId ? $this->users->find((int)$userId) : null;

        $csrfToken = $this->csrfTokens->issue('place_order');
        $html = $this->views->render('order/checkout.php', [
            'cartItems' => $cart->getItems(),
            'totalPrice' => $cart->getTotalPrice(),
            'userName' => $user?->getName() ?? '',
            'userAddress' => $user?->getAddress() ?? '',
            'csrfToken' => $csrfToken,
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function place(Request $request): Response
    {
        $cart = ($_SESSION['cart'] ?? null);
        if (!($cart instanceof Cart) || empty($cart->getItems())) {
            return Response::redirect('/');
        }

        $this->pdo->beginTransaction();
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $user = $userId ? $this->users->find((int)$userId) : null;
            if ($user === null) {
                throw new \RuntimeException('ユーザー情報が見つかりません。');
            }

            $order = new Order($user, $cart);

            $this->orders->save($order);

            foreach ($order->getCartItems() as $item) {
                $this->products->decreaseStock($item['product']->getId(), $item['quantity']);
            }

            unset($_SESSION['cart']);
            $_SESSION['latest_order'] = $order;

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $_SESSION['error_message'] = '注文処理中にエラーが発生しました: ' . $e->getMessage();
            return Response::redirect('/checkout');
        }

        return Response::redirect('/order_complete');
    }

    public function orderComplete(Request $request): Response
    {
        $html = $this->views->render('order/complete.php');

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function orders(Request $request): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return Response::redirect('/login');
        }

        $user = $this->users->find((int)$userId);
        if ($user === null) {
            $_SESSION['error_message'] = 'ユーザー情報が取得できませんでした。';
            return Response::redirect('/login');
        }

        $flash = $_SESSION['error_message'] ?? null;
        unset($_SESSION['error_message']);

        [$currentMonth, $currentMonthString] = $this->resolveMonth(null);

        $orders = $this->orders->findByUserAndMonth($user, $currentMonth);

        $csrfToken = $this->csrfTokens->issue('orders_export');

        $html = $this->views->render('order/list.php', [
            'flash' => $flash,
            'currentMonthString' => $currentMonthString,
            'orders' => $orders,
            'csrfToken' => $csrfToken,
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function exportMonthlyCsv(Request $request): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return Response::redirect('/login');
        }

        $user = $this->users->find((int)$userId);
        if ($user === null) {
            $_SESSION['error_message'] = 'ユーザー情報が取得できませんでした。';
            return Response::redirect('/login');
        }

        [$month, $monthString] = $this->resolveMonth($request->body['month'] ?? null);
        if ($month === null) {
            return Response::redirect('/orders');
        }

        try {
            $document = $this->csvExporter->exportMonthly($user, $month);
        } catch (NoOrdersForExportException $e) {
            $_SESSION['error_message'] = '指定した月の注文は見つかりませんでした。';
            return Response::redirect('/orders');
        }

        return new Response(
            200,
            $document->getContent(),
            [
                'Content-Type' => $document->getContentType(),
                'Content-Disposition' => sprintf('attachment; filename="%s"', $document->getFilename()),
            ]
        );
    }

    private function resolveMonth(?string $rawMonth): array
    {
        $now = new DateTimeImmutable('first day of this month');
        $min = $now->sub(new DateInterval('P23M')); // 含めて24か月分

        if ($rawMonth === null || $rawMonth === '') {
            return [$now, $now->format('Y-m')];
        }

        $month = DateTimeImmutable::createFromFormat('Y-m', $rawMonth);
        $errors = DateTimeImmutable::getLastErrors();
        if ($month === false || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            $_SESSION['error_message'] = '月の指定が不正です。';
            return [null, null];
        }

        $month = $month->setDate((int)$month->format('Y'), (int)$month->format('m'), 1);

        if ($month > $now) {
            $_SESSION['error_message'] = '未来の月は指定できません。';
            return [null, null];
        }

        if ($month < $min) {
            $_SESSION['error_message'] = '過去24か月より前のデータは取得できません。';
            return [null, null];
        }

        return [$month, $month->format('Y-m')];
    }
}
