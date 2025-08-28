<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Product;
use Exception;

final class Cart
{
    private array $cartItems;

    public function __construct() 
    {
        $this->cartItems = [];
    }

    // 商品を追加する機能
     public function addProduct(Product $product, int $quantity): void
    {
        // 1. 在庫チェック (早期リターン)
        if ($quantity > $product->getStock()) {
            throw new Exception("在庫が不足しています。({$product->getName()})");
        }

        // 2. 既存チェック
        $productIdToAdd = $product->getId();
        foreach ($this->cartItems as &$item) {
            if ($item['product']->getId() === $productIdToAdd) {
                // 存在する場合: 数量を加算して、メソッドを終了
                $item['quantity'] += $quantity;
                return; // 参照を使った後は unset するのが丁寧だが、return するなら不要
            }
        }
        // ループ内で参照を使った場合、ループ後に参照を解除するのが安全な作法
        unset($item); 

        // 3. 存在しない場合: 新しくアイテムを追加
        $this->cartItems[] = [
            'product' => $product,
            'quantity' => $quantity,
        ];
    }



    // 商品を削除する機能
    public function removeProduct(Product $product): void
    {
        $productIdToRemove = $product->getId();

        foreach ($this->cartItems as $index => &$cartItem) {
            if ($cartItem['product']->getId() === $productIdToRemove) {
                unset($this->cartItems[$index]);
                unset($cartItem);
                return;
            }
        }
        unset($cartItem);
    }

    // 商品を一覧表示する機能
    public function getItems(): array
    {
        return $this->cartItems;
    }
    
    public function getTotalPrice(): float
    {
        $totalPrice = 0;

        foreach ($this->cartItems as $cartItem) {
            $totalPrice += $cartItem['product']->getPrice() * $cartItem['quantity'];
        }

        return $totalPrice;
    }
}