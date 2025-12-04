<?php

declare(strict_types=1);

use App\Model\Cart;
use App\Model\Coupon\FixedAmountCoupon;
use App\Model\Coupon\RateCoupon;
use App\Model\Product;

require __DIR__ . '/../vendor/autoload.php';

function createProduct(string $name, float $price, int $stock, int $id): Product
{
    $product = Product::createNew($name, $price, $name . ' 説明', $stock);
    $product->setId($id);

    return $product;
}

$cart = new Cart();

$dripSet = createProduct('ハンドドリップセット', 7500, 10, 1);
$beans = createProduct('プレミアムコーヒー豆', 1800, 20, 2);

$cart->addProduct($dripSet, 1);
$cart->addProduct($beans, 2);

printf("小計: %.2f\n", $cart->getSubtotal());

$fixedCoupon = new FixedAmountCoupon('WELCOME-500', 500);
$cart->applyCoupon($fixedCoupon);
printf(
    "固定額クーポン(%s)適用 -> 割引 %.2f / 合計 %.2f\n",
    $fixedCoupon->getCode(),
    $cart->getDiscountAmount(),
    $cart->getTotalPrice()
);

$rateCoupon = new RateCoupon('VIP-15', 0.15);
$cart->applyCoupon($rateCoupon);
printf(
    "率クーポン(%s)適用 -> 割引 %.2f / 合計 %.2f\n",
    $rateCoupon->getCode(),
    $cart->getDiscountAmount(),
    $cart->getTotalPrice()
);
