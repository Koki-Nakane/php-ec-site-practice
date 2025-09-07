<?php

declare(strict_types=1);

namespace App\Model;

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
        foreach ($this->cartItems as $index => $item) {
            if ($item['product']->getId() === $productIdToAdd) {
                $this->cartItems[$index]['quantity'] += $quantity;
                return;
            }
        }

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

        foreach ($this->cartItems as $index => $cartItem) {
            if ($cartItem['product']->getId() === $productIdToRemove) {
                unset($this->cartItems[$index]);
                return;
            }
        }
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
