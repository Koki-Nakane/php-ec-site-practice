<?php

declare(strict_types=1);

use App\Mapper\ProductMapper;
use App\Model\Database;

require_once __DIR__ . '/vendor/autoload.php';
session_start();

try {
    $pdo = Database::getInstance()->getConnection();
    $productMapper = new ProductMapper($pdo);

    $products = $productMapper->findAll();

} catch (PDOException $e) {
    echo 'データベース接続エラー: ' . $e->getMessage();
    die();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品一覧</title>
</head>
<body>
    <h1>商品一覧</h1>
    
    <?php if (empty($products)):?>
        <p>現在、販売中の商品はありません。</p>
    <?php else:?>
        <ul>
            <?php foreach ($products as $product):?>
                <li>
                    <h2><?php echo htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p>価格: <?php echo htmlspecialchars((string)$product->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</p>
                    <p>在庫: <?php echo htmlspecialchars((string)$product->getStock(), ENT_QUOTES, 'UTF-8'); ?>個</p>
                    <p><?php echo nl2br(htmlspecialchars($product->getDescription(), ENT_QUOTES, 'UTF-8')); ?></p>
                    
                    <form action="add_to_cart.php" method="post">
                        <input type="hidden" name="product_id" value="<?php echo $product->getId(); ?>">
                        <label for="quantity-<?php echo $product->getId(); ?>">数量:</label>
                        <input type="number" id="quantity-<?php echo $product->getId(); ?>" name="quantity" value="1" min="1">
                        <button type="submit">カートに入れる</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>