<?php

declare(strict_types=1);
// Productモデル・ProductMapperの動作検証用テストスクリプト
require_once __DIR__ . '/../vendor/autoload.php';
// require_once __DIR__ . '/../my_autoloader.php'; // autoload.phpで十分な場合は不要

use App\Exception\ProductNotFoundException;
use App\Mapper\ProductMapper;

// DB接続情報（必要に応じて修正）
$pdo = new PDO('mysql:host=db;dbname=php-ec-site-practice_db', 'root', 'root_password');
$productMapper = new ProductMapper($pdo);

// テスト1: 商品取得（通常/削除済み含む）
echo "\n--- findIncludingDeleted() ---\n";
try {
    $product = $productMapper->findIncludingDeleted(1); // ID=1の商品
    var_dump($product);
} catch (ProductNotFoundException $e) {
    echo 'ProductNotFoundException: ' . $e->getMessage() . "\n";
}

// テスト2: 管理者用商品一覧
$products = $productMapper->listForAdmin();
echo "\n--- listForAdmin() ---\n";
foreach ($products as $p) {
    echo $p->getId() . ': ' . $p->getName() . ' (isActive=' . ($p->isActive() ? '1' : '0') . ', deletedAt=' . ($p->getDeletedAt() ?? 'null') . ")\n";
}

// テスト3: 商品の有効化/無効化
// $productMapper->enable(1);
// $productMapper->disable(1);

// テスト4: 商品の論理削除/復元
// $productMapper->markDeleted(1);
// $productMapper->restore(1);

// テスト5: 在庫数更新
// $productMapper->updateStock(1, 99);

echo "\nテスト完了\n";
