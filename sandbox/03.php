<?php

/* 3. Cart クラスの作成:
商品をカートに追加、削除、一覧表示する機能を持つ Cart クラスを作成してください。内部では、Product オブジェクトの配列と、その個数を保持するようにしてください。（例: private $items = ['product' => Product, 'quantity' => 2]; のような形式）
*/

declare(strict_types=1);

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
        if ($quantity > $product->getStock()) {
            throw new Exception('在庫が不足しています。');
        }

        // すでに同じ商品がカートに入っているかの判定
        // 入っている場合、そのアイテムのquantityプロパティの値を引数$quantityだけ増やす
        $productIdToAdd = $product->getId();

        foreach ($this->cartItems as &$cartItem) {
            if ($cartItem['product']->getId() === $productIdToAdd) {
                $cartItem['quantity'] += $quantity;
                unset($cartItem);
                return;
            }
        }
        unset($cartItem);
        //入っていない場合、$cartItemsの要素に追加する
        // $Product = $idと対応するインスタンスを探して代入
        $this->cartItems[] = ['product' => $product, 'quantity' => $quantity];
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
}
