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
use DateInterval;
use DateTimeImmutable;

final class OrderController
{
    public function __construct(
        private \PDO $pdo,
        private UserMapper $users,
        private ProductMapper $products,
        private OrderCsvExporter $csvExporter,
        private CsrfTokenManager $csrfTokens,
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

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>注文確認</title>
        </head>
        <body>
            <h1>注文内容の確認</h1>

            <h2>お届け先情報</h2>
            <p>お名前: <?php echo htmlspecialchars($user?->getName() ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <p>ご住所: <?php echo htmlspecialchars($user?->getAddress() ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

            <h2>ご注文商品</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>商品名</th>
                        <th>価格</th>
                        <th>数量</th>
                        <th>小計</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart->getItems() as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product']->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$item['product']->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</td>
                            <td><?php echo htmlspecialchars((string)$item['quantity'], ENT_QUOTES, 'UTF-8'); ?>個</td>
                            <td><?php echo htmlspecialchars((string)($item['product']->getPrice() * $item['quantity']), ENT_QUOTES, 'UTF-8'); ?>円</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h3>合計金額: <?php echo htmlspecialchars((string)$cart->getTotalPrice(), ENT_QUOTES, 'UTF-8'); ?>円</h3>

            <form action="/place_order" method="post">
                <button type="submit">この内容で注文を確定する</button>
            </form>

        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
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

            $orderMapper = new OrderMapper($this->pdo, $this->products);
            $orderMapper->save($order);

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
        ob_start();
        ?>
                <!DOCTYPE html>
                <html lang="ja">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>ご注文完了</title>
                    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
                </head>
                <body>
                    <h1>ご注文ありがとうございました！</h1>
                    <p>ご注文が正常に完了しました。</p>
                    <p><a href="/">トップへ戻る</a></p>
                </body>
                </html>
                <?php
        $html = (string) ob_get_clean();
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

        $mapper = new OrderMapper($this->pdo, $this->products);
        $orders = $mapper->findByUserAndMonth($user, $currentMonth);

        $csrfToken = $this->csrfTokens->issue('orders_export');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>注文履歴</title>
            <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
            <style>
                body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 2rem; }
                form { margin-bottom: 1.5rem; display: flex; gap: 0.5rem; align-items: flex-end; }
                label { display: grid; gap: .25rem; }
                input[type="month"] { padding: .35rem .5rem; }
                button { padding: .45rem .9rem; border: 0; border-radius: 4px; background: #0b5ed7; color: #fff; cursor: pointer; }
                button:hover { background: #0a53be; }
                table { border-collapse: collapse; width: 100%; max-width: 960px; }
                th, td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; }
                th { background: #f7f7f7; }
                .flash { background: #fee; color: #b00; border: 1px solid #fbb; padding: .75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
            </style>
        </head>
        <body>
            <h1>注文履歴</h1>

            <?php if ($flash !== null): ?>
                <div class="flash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form action="/orders/export" method="post">
                <label>
                    対象月
                    <input type="month" name="month" value="<?php echo htmlspecialchars($currentMonthString, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($currentMonthString, ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">CSVをダウンロード</button>
            </form>

            <?php if (empty($orders)): ?>
                <p>今月のご注文はまだありません。</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>注文ID</th>
                            <th>注文日時</th>
                            <th>合計金額</th>
                            <th>商品一覧</th>
                            <th>配送先</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php $items = []; ?>
                            <?php foreach ($order->getCartItems() as $item): ?>
                                <?php $items[] = sprintf('%s×%d', $item['product']->getName(), $item['quantity']); ?>
                            <?php endforeach; ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$order->getId(), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($order->getDate()->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$order->getTotalPrice(), ENT_QUOTES, 'UTF-8'); ?>円</td>
                                <td><?php echo htmlspecialchars(implode('; ', $items), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($order->getShippingAddress(), ENT_QUOTES, 'UTF-8')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </body>
        </html>
        <?php
        $html = (string) ob_get_clean();
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

        if (!$this->csrfTokens->validate('orders_export', $request->body['_token'] ?? null)) {
            $_SESSION['error_message'] = 'フォームの有効期限が切れました。もう一度お試しください。';
            return Response::redirect('/orders');
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
