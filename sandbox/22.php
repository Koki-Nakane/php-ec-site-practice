<?php

/* 22. 商品API (api/products.php):
GETリクエストに対し、全商品の情報をJSON形式で返すAPIを作成してください。ヘッダーを Content-Type: application/json に設定するのを忘れないでください。
*/

declare(strict_types=1);

use App\Mapper\ProductMapper;
use App\Model\Database;

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

$pdo = Database::getInstance()->getConnection();

$productMapper = new ProductMapper($pdo);

$products = $productMapper->findAll();

foreach ($products as $product) {
    $data[] = $product->toArray();
}

$jsonString = json_encode($data);

echo $jsonString;
