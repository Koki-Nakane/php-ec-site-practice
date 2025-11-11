<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Enum\OrderStatus;
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
        if ($order->getId() === null) {
            $this->insert($order);
            return;
        }

        $this->update($order);
    }

    private function insert(Order $order): void
    {
        try {
            $this->pdo->beginTransaction();

            $sqlOrders = 'INSERT INTO orders (user_id, total_price, shipping_address, status) VALUES (?, ?, ?, ?)';
            $stmtOrders = $this->pdo->prepare($sqlOrders);
            $userId = $order->getUser()->getId();
            if ($userId === null) {
                throw new \InvalidArgumentException('ユーザーIDが設定されていません。');
            }

            $stmtOrders->execute([
                $userId,
                $order->getTotalPrice(),
                $order->getShippingAddress(),
                $order->getStatus(),
            ]);

            $orderId = (int) $this->pdo->lastInsertId();
            $order->setId($orderId);

            $sqlItems = 'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)';
            $stmtItems = $this->pdo->prepare($sqlItems);

            foreach ($order->getCartItems() as $item) {
                $product = $this->productMapper->findIncludingDeleted($item['product']->getId());

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

    private function update(Order $order): void
    {
        if ($order->getId() === null) {
            throw new \InvalidArgumentException('注文IDが指定されていません。');
        }

        $sql = 'UPDATE orders SET shipping_address = :shipping_address, status = :status WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':shipping_address' => $order->getShippingAddress(),
            ':status' => $order->getStatus(),
            ':id' => $order->getId(),
        ]);

        $order->setUpdatedAt(new DateTimeImmutable());
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

        $sql = 'SELECT id, total_price, shipping_address, status, created_at, updated_at, deleted_at FROM orders WHERE user_id = :user_id AND deleted_at IS NULL AND created_at >= :start AND created_at < :end ORDER BY created_at ASC';
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
                (int)$row['status'],
                $row['updated_at'],
                $row['deleted_at'],
                (int)$row['id']
            );
        }

        return $orders;
    }

    /**
     * @return Order[]
     */
    public function listForAdmin(?int $status = null, ?bool $onlyDeleted = null, int $limit = 100, int $offset = 0): array
    {
        if ($status !== null && !OrderStatus::isValid($status)) {
            $status = null;
        }

        $conditions = [];
        $params = [];

        if ($status !== null) {
            $conditions[] = 'o.status = :status';
            $params[':status'] = $status;
        }

        if ($onlyDeleted === true) {
            $conditions[] = 'o.deleted_at IS NOT NULL';
        } elseif ($onlyDeleted === false) {
            $conditions[] = 'o.deleted_at IS NULL';
        }

        $sql = 'SELECT 
                    o.id AS order_id,
                    o.user_id,
                    o.total_price,
                    o.shipping_address,
                    o.status,
                    o.created_at,
                    o.updated_at,
                    o.deleted_at AS order_deleted_at,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.password AS user_password,
                    u.address AS user_address,
                    u.is_admin AS user_is_admin,
                    u.deleted_at AS user_deleted_at
                FROM orders o
                INNER JOIN users u ON u.id = o.user_id';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrateOrderForAdmin'], $rows);
    }

    public function findForAdmin(int $id): ?Order
    {
        $sql = 'SELECT 
                    o.id AS order_id,
                    o.user_id,
                    o.total_price,
                    o.shipping_address,
                    o.status,
                    o.created_at,
                    o.updated_at,
                    o.deleted_at AS order_deleted_at,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.password AS user_password,
                    u.address AS user_address,
                    u.is_admin AS user_is_admin,
                    u.deleted_at AS user_deleted_at
                FROM orders o
                INNER JOIN users u ON u.id = o.user_id
                WHERE o.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateOrderForAdmin($row);
    }

    public function markDeleted(int $id, DateTimeImmutable $when): ?Order
    {
        $order = $this->findForAdmin($id);
        if ($order === null) {
            return null;
        }

        if ($order->isDeleted()) {
            return $order;
        }

        $order->markDeleted($when);

        $stmt = $this->pdo->prepare('UPDATE orders SET deleted_at = :deleted_at WHERE id = :id');
        $stmt->execute([
            ':deleted_at' => $when->format('Y-m-d H:i:s'),
            ':id' => $id,
        ]);

        $order->setUpdatedAt(new DateTimeImmutable());

        return $order;
    }

    public function restore(int $id, DateTimeImmutable $when): ?Order
    {
        $order = $this->findForAdmin($id);
        if ($order === null) {
            return null;
        }

        if (!$order->isDeleted()) {
            return $order;
        }

        $order->restore();

        $stmt = $this->pdo->prepare('UPDATE orders SET deleted_at = NULL WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $order->setUpdatedAt(new DateTimeImmutable());

        return $order;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateOrderForAdmin(array $row): Order
    {
        $user = $this->hydrateUserFromRow($row);
        $items = $this->findItemsForOrder((int)$row['order_id']);

        return Order::fromSnapshot(
            $user,
            $items,
            (float) $row['total_price'],
            (string) $row['shipping_address'],
            (string) $row['created_at'],
            (int) $row['status'],
            (string) $row['updated_at'],
            $row['order_deleted_at'],
            (int) $row['order_id']
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateUserFromRow(array $row): User
    {
        return User::createFromDbRow([
            'id' => $row['user_id'],
            'name' => $row['user_name'],
            'email' => $row['user_email'],
            'password' => $row['user_password'],
            'address' => $row['user_address'],
            'is_admin' => $row['user_is_admin'],
            'deleted_at' => $row['user_deleted_at'],
        ]);
    }

    private function findItemsForOrder(int $orderId): array
    {
        $sql = 'SELECT product_id, quantity, price FROM order_items WHERE order_id = :order_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);

        $items = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $product = $this->productMapper->findIncludingDeleted((int)$row['product_id']);

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
