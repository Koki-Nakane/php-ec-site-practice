<?php

/* 16. カート追加処理 (add_to_cart.php):
商品一覧ページからのリクエストを受け取り、指定された商品と数量をセッションの Cart に追加してください。Product の在庫チェックも忘れずに行ってください。
*/

declare(strict_types=1);

use App\Mapper\ProductMapper;
use App\Model\Cart;
use App\Model\Database;

require_once __DIR__ . '/vendor/autoload.php';

session_start();

try {
    // 3. データベース接続とマッパーを準備
    $pdo = Database::getInstance()->getConnection();
    $productMapper = new ProductMapper($pdo);

    // 4. セッションからカートオブジェクトを準備 (なければ新規作成)
    if (isset($_SESSION['cart']) && $_SESSION['cart'] instanceof Cart) {
        $cart = $_SESSION['cart'];
    } else {
        $cart = new Cart();
    }

    // 5. POSTリクエストから商品IDと数量を取得 (バリデーション)
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    // IDまたは数量が無効な場合はエラー
    if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
        throw new Exception('無効な商品IDまたは数量です。');
    }

    // 6. データベースから商品オブジェクトを取得
    $product = $productMapper->find($productId);
    if ($product === null) {
        throw new Exception('指定された商品が見つかりません。');
    }

    // 7. Cartクラスのメソッドを使って商品を追加 (ここで在庫チェックが行われる)
    $cart->addProduct($product, $quantity);

    // 8. 更新されたカートオブジェクトをセッションに保存
    $_SESSION['cart'] = $cart;

} catch (Exception $e) {
    // エラーが発生した場合は、メッセージをセッションに保存して商品一覧ページに戻る
    // (こうすることで、index.php側でエラーメッセージを表示できる)
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: /index.php');
    exit;
}

// 9. 正常に処理が完了したら、カート表示ページにリダイレクト
header('Location: /cart.php');
exit;
