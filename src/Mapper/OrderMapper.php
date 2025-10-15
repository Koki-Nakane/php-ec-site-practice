<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Order;
use App\Model\User;
use DateTimeImmutable;

final class OrderMapper
{
    public function __construct(
        private \PDO $pdo,
        private ProductMapper $productMapper
    ) {
    }

    public function save(Order $order): void
    {
        try {
            $this->pdo->beginTransaction();

            $sqlOrders = 'INSERT INTO orders (user_id, total_price, shipping_address) VALUES (?, ?, ?)';
            $stmtOrders = $this->pdo->prepare($sqlOrders);
            $userId = $order->getUser()->getId();
            $totalPrice = $order->getTotalPrice();
            $stmtOrders->execute([$userId, $totalPrice, $order->getShippingAddress()]);
            $orderId = $this->pdo->lastInsertId();
            $order->setId((int)$orderId);

            $sqlItems = 'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)';
            $stmtItems = $this->pdo->prepare($sqlItems);

            foreach ($order->getCartItems() as $item) {
                $product = $this->productMapper->find($item['product']->getId());

                if ($product === null) {
                    throw new \RuntimeException('Product not found for order item.');
                }

                $stmtItems->execute([
                    $orderId,
                    $product->getId(),
                    $item['quantity'],
                    $product->getPrice(),
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    /**
     * @return Order[]
     */
    public function findByUserAndMonth(User $user, DateTimeImmutable $month): array
    {
        $userId = $user->getId();

        if ($userId === null) {
            throw new \InvalidArgumentException('User ID is required to fetch orders.');
        }

        $startOfMonth = $month->setDate(
            (int)$month->format('Y'),
            (int)$month->format('m'),
            1
        )->setTime(0, 0, 0);

        $start = $startOfMonth->format('Y-m-d H:i:s');
        $end = $startOfMonth->modify('+1 month')->format('Y-m-d H:i:s');

        $sql = 'SELECT id, total_price, shipping_address, created_at FROM orders WHERE user_id = :user_id AND created_at >= :start AND created_at < :end ORDER BY created_at ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':start' => $start,
            ':end' => $end,
        ]);

        $orders = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $items = $this->findItemsForOrder((int)$row['id']);

            $orders[] = Order::fromSnapshot(
                $user,
                $items,
                (float)$row['total_price'],
                $row['shipping_address'],
                $row['created_at'],
                (int)$row['id']
            );
        }

        return $orders;
    }

    /**
     * @return array<int, array{product: \App\Model\Product, quantity: int, price: float}>
     */
    private function findItemsForOrder(int $orderId): array
    {
        $sql = 'SELECT product_id, quantity, price FROM order_items WHERE order_id = :order_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);

        $items = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $product = $this->productMapper->find((int)$row['product_id']);

            if ($product === null) {
                continue;
            }

            $items[] = [
                'product' => $product,
                'quantity' => (int)$row['quantity'],
                'price' => (float)$row['price'],
            ];
        }

        return $items;
    }
}
