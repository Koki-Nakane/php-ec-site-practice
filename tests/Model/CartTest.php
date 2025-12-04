<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\Cart;
use App\Model\Product;
use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase
{
    public function testAddProductAggregatesQuantityWhenSameItemAddedTwice(): void
    {
        $cart = new Cart();
        $product = $this->createProduct('ハンドドリップセット', 7500, 10);

        $cart->addProduct($product, 2);
        $cart->addProduct($product, 1);

        $items = $cart->getItems();

        $this->assertCount(1, $items);
        $this->assertSame(3, $items[0]['quantity']);
    }

    public function testAddProductThrowsExceptionWhenStockIsInsufficient(): void
    {
        $cart = new Cart();
        $product = $this->createProduct('限定コーヒー豆', 1800, 2);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('在庫が不足しています。');

        $cart->addProduct($product, 3);
    }

    public function testGetTotalPriceSumsPriceAcrossItems(): void
    {
        $cart = new Cart();
        $productA = $this->createProduct('サーバーセット', 4500, 5);
        $productB = $this->createProduct('計量スプーン', 900, 10);

        $cart->addProduct($productA, 1); // 4,500
        $cart->addProduct($productB, 3); // 2,700

        $this->assertSame(7200.0, $cart->getTotalPrice());
    }

    private function createProduct(string $name, float $price, int $stock): Product
    {
        static $nextId = 1;

        $product = Product::createNew($name, $price, $name . ' 説明', $stock);
        $product->setId($nextId++);

        return $product;
    }
}
