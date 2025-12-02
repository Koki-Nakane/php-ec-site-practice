<?php

declare(strict_types=1);

use App\Model\Database;
use App\Model\Enum\OrderStatus;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "このスクリプトは CLI からのみ実行できます\n");
    exit(1);
}

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$userName = 'browser_tester';
$productName = 'レビュー用ハンドドリップセット';
$productDescription = 'Problem 44 動作確認用の限定セットです。フィルター、ドリッパー、温度計付きケトルを含みます。';
$productPrice = 15800.00;
$productStock = 25;

try {
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare('SELECT id, email, address FROM users WHERE name = :name LIMIT 1');
    $userStmt->execute([':name' => $userName]);
    $user = $userStmt->fetch(\PDO::FETCH_ASSOC);

    if ($user === false) {
        throw new \RuntimeException("ユーザー {$userName} が見つかりません。scripts/create_test_user.php で作成してください。");
    }

    $userId = (int) $user['id'];
    $shippingAddress = (string) ($user['address'] ?: '東京都テスト区1-2-3 レビュー邸');

    $productStmt = $pdo->prepare('SELECT id FROM products WHERE name = :name LIMIT 1');
    $productStmt->execute([':name' => $productName]);
    $productRow = $productStmt->fetch(\PDO::FETCH_ASSOC);

    $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

    if ($productRow === false) {
        $insertProduct = $pdo->prepare(
            'INSERT INTO products (name, price, description, stock, is_active, deleted_at, created_at, updated_at)
             VALUES (:name, :price, :description, :stock, 1, NULL, :created_at, :updated_at)'
        );
        $insertProduct->execute([
            ':name' => $productName,
            ':price' => $productPrice,
            ':description' => $productDescription,
            ':stock' => $productStock,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $productId = (int) $pdo->lastInsertId();
        fwrite(STDOUT, "[products] inserted: {$productName} (ID={$productId})\n");
    } else {
        $productId = (int) $productRow['id'];
        $updateProduct = $pdo->prepare(
            'UPDATE products
             SET price = :price,
                 description = :description,
                 stock = :stock,
                 is_active = 1,
                 deleted_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $updateProduct->execute([
            ':price' => $productPrice,
            ':description' => $productDescription,
            ':stock' => $productStock,
            ':updated_at' => $now,
            ':id' => $productId,
        ]);
        fwrite(STDOUT, "[products] updated: {$productName} (ID={$productId})\n");
    }

    $existingOrderStmt = $pdo->prepare(
        'SELECT o.id
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = :user_id
           AND oi.product_id = :product_id
           AND (o.deleted_at IS NULL)
         LIMIT 1'
    );
    $existingOrderStmt->execute([
        ':user_id' => $userId,
        ':product_id' => $productId,
    ]);
    $existingOrder = $existingOrderStmt->fetch(\PDO::FETCH_ASSOC);

    if ($existingOrder === false) {
        $insertOrder = $pdo->prepare(
            'INSERT INTO orders (user_id, total_price, shipping_address, status, created_at, updated_at, deleted_at)
             VALUES (:user_id, :total_price, :shipping_address, :status, :created_at, :updated_at, NULL)'
        );
        $insertOrder->execute([
            ':user_id' => $userId,
            ':total_price' => $productPrice,
            ':shipping_address' => $shippingAddress,
            ':status' => OrderStatus::COMPLETED,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, price)
             VALUES (:order_id, :product_id, :quantity, :price)'
        );
        $insertItem->execute([
            ':order_id' => $orderId,
            ':product_id' => $productId,
            ':quantity' => 1,
            ':price' => $productPrice,
        ]);

        fwrite(STDOUT, "[orders] created order #{$orderId} for {$userName}\n");
    } else {
        $orderId = (int) $existingOrder['id'];
        fwrite(STDOUT, "[orders] already exists order #{$orderId} for {$userName} and {$productName}\n");
    }

    $pdo->commit();

    fwrite(STDOUT, "セットアップ完了: 商品 '{$productName}' を追加し、{$userName} が購入済みになりました。\n");
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'エラー: ' . $e->getMessage() . "\n");
    exit(1);
}
