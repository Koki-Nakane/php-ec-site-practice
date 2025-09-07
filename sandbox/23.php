<?php

/* 23. 特定の商品API (api/product.php):
id パラメータを受け取り、指定されたIDの Product 情報のみをJSONで返すAPIを作成してください。該当商品がなければ404エラーを返してください。
*/

declare(strict_types=1);

use App\Mapper\ProductMapper;
use App\Model\Database;
use App\Model\Product;

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

$pdo = Database::getInstance()->getConnection();

$productMapper = new ProductMapper($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === null || $id === false || $id <= 0) {
    http_response_code(400);
    $error = ['error' => 'Invalid or missing ID parameter'];
    echo json_encode($error);
    exit;
}

$product = $productMapper->find($id);

if ($product === null) {
    # 404エラーを返す
    http_response_code(404);

    $error = ['error' => 'Product not found'];
    echo json_encode($error);

    exit;
}

$data = $product->toArray();

$jsonString = json_encode($data);

echo $jsonString;
