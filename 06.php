<?php
/* 6. ProductMapper クラスの作成 (データマッパーパターン):
Database クラスを利用して、products テーブルから Product オブジェクトを取得 (find(int $id))、保存 (save(Product $product))、削除 (delete(Product $product)) する責務を持つ ProductMapper クラスを作成してください。
*/

declare(strict_types=1);

use App\Model\Database;
use App\Model\Product;

require_once __DIR__ . '/vendor/autoload.php';

final class ProductMapper
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(int $id): ?Product
    {
        $sql = "SELECT * FROM products WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row){
            return null;
        }
        
        return new Product(
            (int)$row['id'],
            $row['name'],
            (float)$row['price'],
            $row['description'],
            (int)$row['stock']
        );
    }

    public function save(Product $product): void
    {
        if ($product->getId() === null || $product->getId() === 0) {
            $this->insert($product);
        } else {
            $this->update($product);
        }
    }

    private function insert(Product $product): void
    {
        $sql = "INSERT INTO products (name, price, description, stock) VALUES (:name, :price, :description, :stock)";
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
        $sql = "UPDATE products SET name = :name, price = :price, description = :description, stock = :stock WHERE id = :id";

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
        $sql = "DELETE FROM products WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$product->getId()]);
    }
}