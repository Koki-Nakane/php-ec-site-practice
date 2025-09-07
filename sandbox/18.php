<?php
/* 18. 注文処理 (checkout.php):
ログインしているユーザーのみがアクセスできるように、AuthController の isAuthenticated() でチェックしてください。ログインしていない場合は、ログインページにリダイレクトしてください。
*/

/*
 * 注文処理ページ (checkout.php)
 *
 * このページの役割:
 * 1. ユーザーがログインしているかを確認する。
 * 2. ログインしていなければ、ログインページへ強制的に移動させる。
 * 3. ログインしていれば、注文情報を確認し、注文を確定するためのフォームを表示する。
 */
declare(strict_types=1);

// 2. 必要なクラスをインポート
use App\Controller\AuthController;
use App\Mapper\UserMapper;
use App\Model\Cart;
use App\Model\Database;

// 1. 準備 (セッション開始とオートロード)
require_once __DIR__ . '/vendor/autoload.php';
session_start();

// 3. AuthControllerの準備 (UserMapperが必要)
$pdo = Database::getInstance()->getConnection();
$userMapper = new UserMapper($pdo);
$authController = new AuthController($userMapper);

// 4. ★★★ 認証チェック ★★★
//    isAuthenticated() メソッドでログイン状態を確認
if (!$authController->isAuthenticated()) {
    // ログインしていなければ、ログインページにリダイレクトして処理を中断
    header('Location: /login.php');
    exit;
}

// 5. カートの中身を確認
// (カートが空の状態で注文できないようにするチェック)
if (isset($_SESSION['cart']) && $_SESSION['cart'] instanceof Cart) {
    $cart = $_SESSION['cart'];
} else {
    $cart = new Cart();
}

if (empty($cart->getItems())) {
    // カートが空の場合は、商品一覧ページに戻す
    $_SESSION['error_message'] = 'カートが空です。';
    header('Location: /index.php');
    exit;
}

// 6. ログインしているユーザーの情報を取得 (表示用)
$user = $userMapper->find($_SESSION['user_id']);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文確認</title>
</head>
<body>
    <h1>注文内容の確認</h1>

    <h2>お届け先情報</h2>
    <p>お名前: <?php echo htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8'); ?></p>
    <p>ご住所: <?php echo htmlspecialchars($user->getAddress(), ENT_QUOTES, 'UTF-8'); ?></p>

    <h2>ご注文商品</h2>
    <table border="1">
        <thead>
            <tr>
                <th>商品名</th>
                <th>価格</th>
                <th>数量</th>
                <th>小計</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cart->getItems() as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product']->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$item['product']->getPrice(), ENT_QUOTES, 'UTF-8'); ?>円</td>
                    <td><?php echo htmlspecialchars((string)$item['quantity'], ENT_QUOTES, 'UTF-8'); ?>個</td>
                    <td><?php echo htmlspecialchars((string)($item['product']->getPrice() * $item['quantity']), ENT_QUOTES, 'UTF-8'); ?>円</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h3>合計金額: <?php echo htmlspecialchars((string)$cart->getTotalPrice(), ENT_QUOTES, 'UTF-8'); ?>円</h3>

    <!-- 注文確定処理を行う place_order.php へデータを送信するフォーム -->
    <form action="place_order.php" method="post">
        <button type="submit">この内容で注文を確定する</button>
    </form>

</body>
</html>