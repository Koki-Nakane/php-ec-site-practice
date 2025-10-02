<?php

declare(strict_types=1);

use App\Mapper\ProductMapper;
use App\Model\Database;

require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

$pdo = Database::getInstance()->getConnection();

$productMapper = new ProductMapper($pdo);

$products = $productMapper->findAll();

$data = [];
foreach ($products as $product) {
    $data[] = $product->toArray();
}

echo json_encode($data);
