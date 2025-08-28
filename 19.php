<?php
/* 19. 注文確定処理 (place_order.php):
checkout.php からのPOSTリクエストを受け取ります。Order オブジェクトを生成し、Cart の内容と現在の User 情報を設定してください。
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

    // --- ここから先は、将来の問題で実装 ---
    // $orderMapper = new OrderMapper($pdo);
    // $orderMapper->save($order); // DBに注文を保存
    // ------------------------------------
    
    // 注文が完了したので、カートを空にする
    unset($_SESSION['cart']);
    
    // 生成したOrderオブジェクトをセッションに一時的に保存し、
    // サンクスページで注文内容を表示できるようにする
    $_SESSION['latest_order'] = $order;
    
} catch (Exception $e) {
    // エラー処理
    $_SESSION['error_message'] = '注文処理中にエラーが発生しました: ' . $e->getMessage();
    header('Location: /checkout.php'); // エラーがあれば確認ページに戻す
    exit;
}

// 5. サンクスページへリダイレクト
header('Location: /order_complete.php');
exit;