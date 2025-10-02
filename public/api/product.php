<?php

declare(strict_types=1);

use App\Mapper\ProductMapper;
use App\Model\Database;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

$pdo = Database::getInstance()->getConnection();

$productMapper = new ProductMapper($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === null || $id === false || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing ID parameter']);
    exit;
}

$product = $productMapper->find($id);

if ($product === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
    exit;
}

echo json_encode($product->toArray());
