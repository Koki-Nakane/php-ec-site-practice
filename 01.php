<?php
/* 1. Product クラスの拡張:
id, name, price, description (商品説明)、stock (在庫数) のプロパティを持つ Product クラスを作成してください。price と stock は、コンストラクタやセッターで 0 未満の値が設定されないようバリデーションを行ってください。
*/

declare(strict_types=1);

final class Product
{
    private function validateNotNegative(int|float $value, $name): void
    {
        if ($value < 0) {
                throw new InvalidArgumentException("{$name}には0以上の値を指定してください。");
            }
    }

    public function __construct(
        private int $id,
        private string $name,
        private float $price,
        private string $description,
        private int $stock
    ) {
        $this->validateNotNegative($this->price, '価格');
        $this->validateNotNegative($this->stock, '在庫数');
    }

    public function setPrice(float $price): void
    {
        $this->validateNotNegative($price, '価格');
        $this->price = $price;
    }

    public function setStock(int $stock): void
    {
        $this->validateNotNegative($stock, '在庫数');
        $this->stock = $stock;
    }
}
