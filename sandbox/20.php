<?php

/* 20. OrderMapper の作成:
orders テーブルと order_items テーブルに注文情報を保存する OrderMapper を作成してください。save(Order $order) メソッドは、トランザクションを利用して、両方のテーブルへの書き込みが成功した場合のみコミットするようにしてください。
*/

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Order;
use PDO;

final class OrderMapper
{
    public function __construct(
        private PDO $pdo,
        private ProductMapper $productMapper
    ) {
    }

    public function save(Order $order): void
    {
        try {
            $this->pdo->beginTransaction();

            // ordersテーブルへの処理
            $sqlOrders = 'INSERT INTO orders (user_id, total_price) VALUES (?, ?)';
            $stmtOrders = $this->pdo->prepare($sqlOrders);
            $userId = $order->getUser()->getId();
            $totalPrice = $order->getTotalPrice();
            $stmtOrders->execute([$userId, $totalPrice]);
            $orderId = $this->pdo->lastInsertId();
            $order->setId((int)$orderId);

            $sqlItems = 'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)';
            $stmtItems = $this->pdo->prepare($sqlItems);

            foreach ($order->getCartItems() as $item) {
                $product = $this->productMapper->find($item['product']->getId());

                $stmtItems->execute([
                    $orderId,
                    $product->getId(),
                    $item['quantity'],
                    $product->getPrice()
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
