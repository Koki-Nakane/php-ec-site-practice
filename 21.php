<?php
/* 21. 在庫の更新:
注文が確定したら (OrderMapper->save() が成功したら)、購入された商品の在庫 (stock) を products テーブルから減らす処理を実装してください。これもトランザクションに含めるべきです。
*/

/*
 * 注文確定処理 (place_order.php)
 *
 * このスクリプトの役割:
 * 1. ログイン状態とカートの中身を再チェックする (安全のため)
 * 2. 注文情報 (Orderオブジェクト) を生成する
 * 3. (将来の問題で) 生成したOrderオブジェクトをデータベースに保存する
 * 4. 処理完了後、サンクスページにリダイレクトする
 */

declare(strict_types=1);

use App\Controller\AuthController;
use App\Mapper\ProductMapper;
use App\Mapper\OrderMapper;
use App\Mapper\UserMapper;
use App\Model\Database;
use App\Model\Cart;
use App\Model\Order;

require_once __DIR__ . '/vendor/autoload.php';
session_start();

// 3. 認証とカートの再チェック (セキュリティのための二重チェック)
$pdo = Database::getInstance()->getConnection();
$userMapper = new UserMapper($pdo);
$authController = new AuthController($userMapper);
$productMapper = new ProductMapper($pdo);

if (!$authController->isAuthenticated()) {
    // 万が一、ログアウト状態でアクセスされた場合
    header('Location: /login.php');
    exit;
}

if (isset($_SESSION['cart']) && $_SESSION['cart'] instanceof Cart) {
    $cart = $_SESSION['cart'];
} else {
    // カートが存在しないのに注文しようとした場合
    header('Location: /index.php');
    exit;
}

if (empty($cart->getItems())) {
    // カートが空の場合
    header('Location: /index.php');
    exit;
}

$pdo->beginTransaction();

// 4. ★★★ Orderオブジェクトの生成 ★★★
//    現在のユーザーとカートの情報を元に、新しい注文を作成する
try {
    // ログインしているユーザーのオブジェクトを取得
    $user = $userMapper->find($_SESSION['user_id']);
    if ($user === null) {
        throw new Exception('ユーザー情報が見つかりません。');
    }

    // Orderオブジェクトを生成
    $order = new Order($user, $cart);

    $orderMapper = new OrderMapper($pdo, $productMapper);
    $orderMapper->save($order); // DBに注文を保存

    // Orderオブジェクトのカートアイテムをforeachでループさせ、新しく作ったProductMapperの在庫更新メソッドを、商品ごとに呼び出す！
    foreach ($order->getCartItems() as $item) {
        $productMapper->decreaseStock($item['product']->getId(), $item['quantity']);
    }
    
    // 注文が完了したので、カートを空にする
    unset($_SESSION['cart']);
    
    // 生成したOrderオブジェクトをセッションに一時的に保存し、
    // サンクスページで注文内容を表示できるようにする
    $_SESSION['latest_order'] = $order;
    
    $pdo->commit();
} catch (Exception $e) {
    // 1. まず、データベースの状態を元に戻す
    $pdo->rollBack();

    // 2. ユーザーに表示するためのエラーメッセージをセッションに保存する
    //    こうすることで、リダイレクト先のページでこのメッセージを表示できる
    $_SESSION['error_message'] = '注文処理中にエラーが発生しました: ' . $e->getMessage();
    
    // 3. ユーザーを安全な場所（注文確認ページ）に戻す
    header('Location: /checkout.php');
    exit;
}

// 5. サンクスページへリダイレクト
header('Location: /order_complete.php');
exit;