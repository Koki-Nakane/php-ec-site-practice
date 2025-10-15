<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Product;

final class ProductMapper
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(int $id): ?Product
    {
        $sql = 'SELECT * FROM products WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return new Product(
            $row['name'],
            (float)$row['price'],
            $row['description'],
            (int)$row['stock'],
            (int)$row['id']
        );
    }

    public function findAll(): array
    {
        $sql = 'SELECT * FROM products';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $products = [];

        foreach ($rows as $row) {
            $products[] = new Product(
                $row['name'],
                (float)$row['price'],
                $row['description'],
                (int)$row['stock'],
                (int)$row['id']
            );
        }
        return $products;
    }

    public function save(Product $product): void
    {
        if ($product->getId() === null) {
            $this->insert($product);
        } else {
            $this->update($product);
        }
    }

    private function insert(Product $product): void
    {
        $sql = 'INSERT INTO products (name, price, description, stock) VALUES (:name, :price, :description, :stock)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $product->getName(),
            ':price' => $product->getPrice(),
            ':description' => $product->getDescription(),
            ':stock' => $product->getStock(),
        ]);

        $id = $this->pdo->lastInsertId();
        $product->setId((int)$id);
    }

    private function update(Product $product): void
    {
        $sql = 'UPDATE products SET name = :name, price = :price, description = :description, stock = :stock WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':name' => $product->getName(),
            ':price' => $product->getPrice(),
            ':description' => $product->getDescription(),
            ':stock' => $product->getStock(),
            ':id' => $product->getId(),
        ]);
    }

    public function delete(Product $product): void
    {
        $sql = 'DELETE FROM products WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$product->getId()]);
    }

    public function decreaseStock(int $productId, int $quantity): void
    {
        $sql = 'UPDATE products SET stock = stock - ? WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$quantity, $productId]);
    }
}
