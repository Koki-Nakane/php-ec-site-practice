<?php
/* 10. Order クラスの作成:
注文情報を保持する Order クラスを作成してください。User オブジェクト、Cart の内容（商品と数量）、合計金額、注文日時をプロパティとして持つようにします。
*/

declare(strict_types=1);

use App\Model\User;
use App\Model\Cart;
use DateTime;

final class Order
{
    private User $user;
    private array $cartItems;
    private float $totalPrice;
    private DateTime $date;

    public function __construct(
        User $user,
        Cart $cart
    ) {
        $this->user = $user;
        $this->cartItems = $cart->getItems();
        $this->totalPrice = $cart->getTotalPrice();
        $this->date = new DateTime();
    }
}
