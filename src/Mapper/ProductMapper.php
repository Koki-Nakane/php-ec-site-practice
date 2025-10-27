<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Exception\ConcurrentUpdateException;
use App\Exception\ProductNotFoundException;
use App\Model\Product;
use DateTimeImmutable;
use DomainException;
use PDO;

final class ProductMapper
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(int $id): ?Product
    {
        $sql = 'SELECT * FROM products WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateProduct($row) : null;
    }

    public function findIncludingDeleted(int $id): ?Product
    {
        $sql = 'SELECT * FROM products WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateProduct($row) : null;
    }

    public function findActive(int $id): ?Product
    {
        $sql = 'SELECT * FROM products WHERE id = :id AND deleted_at IS NULL AND is_active = 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateProduct($row) : null;
    }

    public function findAll(): array
    {
        return $this->listForStorefront();
    }

    public function listForAdmin(?bool $onlyActive = null, int $limit = 50, int $offset = 0): array
    {
        $conditions = [];

        if ($onlyActive === true) {
            $conditions[] = 'is_active = 1';
        } elseif ($onlyActive === false) {
            $conditions[] = 'is_active = 0';
        }

        $sql = 'SELECT * FROM products';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY updated_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrateProduct'], $rows);
    }

    public function listForStorefront(int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM products WHERE deleted_at IS NULL AND is_active = 1 ORDER BY updated_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrateProduct'], $rows);
    }

    /**
     * @return array<int, Product>
     */
    public function findAllByIds(array $ids, bool $forUpdate = false): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'SELECT * FROM products WHERE id IN (%s) %s',
            $placeholders,
            $forUpdate ? 'FOR UPDATE' : ''
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $products = [];
        foreach ($rows as $row) {
            $products[(int)$row['id']] = $this->hydrateProduct($row);
        }

        return $products;
    }

    public function save(Product $product): void
    {
        if ($product->getId() === null) {
            $this->insert($product);
            return;
        }

        if ($product->isDeleted()) {
            throw new DomainException('削除済みの商品は保存できません。');
        }

        $product->touch(new DateTimeImmutable());
        $this->update($product);
    }

    public function markDeleted(int $id, DateTimeImmutable $when): Product
    {
        $product = $this->lockProduct($id);
        $product->markDeleted($when);
        $product->touch($when);
        $this->update($product);

        return $product;
    }

    public function restore(int $id, DateTimeImmutable $when): Product
    {
        $product = $this->lockProduct($id);
        $product->restore();
        $product->touch($when);
        $this->update($product);

        return $product;
    }

    public function enable(int $id, DateTimeImmutable $when): Product
    {
        $product = $this->lockProduct($id);
        if ($product->isActive()) {
            return $product;
        }
        $product->activate();
        $product->touch($when);
        $this->update($product);

        return $product;
    }

    public function disable(int $id, DateTimeImmutable $when): Product
    {
        $product = $this->lockProduct($id);
        if (!$product->isActive()) {
            return $product;
        }
        $product->deactivate();
        $product->touch($when);
        $this->update($product);

        return $product;
    }

    public function updateStock(int $id, int $adjustment, bool $forUpdate = true): Product
    {
        $product = $forUpdate ? $this->lockProduct($id) : $this->requireProduct($this->findIncludingDeletedRow($id), $id);
        $product->adjustStock($adjustment);
        $product->touch(new DateTimeImmutable());
        $this->update($product);

        return $product;
    }

    public function decreaseStock(int $productId, int $quantity): void
    {
        $this->updateStock($productId, -$quantity);
    }

    public function lockProduct(int $id): Product
    {
        $sql = 'SELECT * FROM products WHERE id = :id FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ProductNotFoundException("商品が見つかりません: {$id}");
        }

        return $this->hydrateProduct($row);
    }

    private function insert(Product $product): void
    {
        $sql = 'INSERT INTO products (name, price, description, stock, is_active, deleted_at, created_at, updated_at)
                VALUES (:name, :price, :description, :stock, :is_active, :deleted_at, :created_at, :updated_at)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $product->getName(),
            ':price' => $product->getPrice(),
            ':description' => $product->getDescription(),
            ':stock' => $product->getStock(),
            ':is_active' => $product->isActive() ? 1 : 0,
            ':deleted_at' => $this->formatNullableDate($product->getDeletedAt()),
            ':created_at' => $this->formatDate($product->getCreatedAt()),
            ':updated_at' => $this->formatDate($product->getUpdatedAt()),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $product->setId($id);
    }

    private function update(Product $product): void
    {
        $sql = 'UPDATE products
                SET name = :name,
                    price = :price,
                    description = :description,
                    stock = :stock,
                    is_active = :is_active,
                    deleted_at = :deleted_at,
                    updated_at = :updated_at
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $product->getName(),
            ':price' => $product->getPrice(),
            ':description' => $product->getDescription(),
            ':stock' => $product->getStock(),
            ':is_active' => $product->isActive() ? 1 : 0,
            ':deleted_at' => $this->formatNullableDate($product->getDeletedAt()),
            ':updated_at' => $this->formatDate($product->getUpdatedAt()),
            ':id' => $product->getId(),
        ]);

        if ($stmt->rowCount() === 0) {
            $id = (int)$product->getId();
            if (!$this->productExists($id)) {
                throw new ProductNotFoundException("商品が見つかりません: {$id}");
            }

            throw new ConcurrentUpdateException('同時更新が発生しました。再度お試しください。');
        }
    }

    private function hydrateProduct(array $row): Product
    {
        return Product::rehydrate($row);
    }

    private function requireProduct(?array $row, int $id): Product
    {
        if (!$row) {
            throw new ProductNotFoundException("商品が見つかりません: {$id}");
        }

        return $this->hydrateProduct($row);
    }

    private function findIncludingDeletedRow(int $id): ?array
    {
        $sql = 'SELECT * FROM products WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        return $date->format(self::DATE_FORMAT);
    }

    private function formatNullableDate(?DateTimeImmutable $date): ?string
    {
        return $date?->format(self::DATE_FORMAT);
    }

    private function productExists(int $id): bool
    {
        $sql = 'SELECT 1 FROM products WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }
}
