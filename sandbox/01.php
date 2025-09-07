<?php

/* 1. Product クラスの拡張:
id, name, price, description (商品説明)、stock (在庫数) のプロパティを持つ Product クラスを作成してください。price と stock は、コンストラクタやセッターで 0 未満の値が設定されないようバリデーションを行ってください。
*/

declare(strict_types=1);

namespace App\Model;

use Exception;
use InvalidArgumentException;

final class Product
{
    private ?int $id;
    private string $name;
    private float $price;
    private string $description;
    private int $stock;

    public function __construct(
        string $name,
        float $price,
        string $description,
        int $stock,
        ?int $id = null
    ) {
        $this->validateNotNegative($price, '価格');
        $this->validateNotNegative($stock, '在庫数');

        $this->name = $name;
        $this->price = $price;
        $this->description = $description;
        $this->stock = $stock;

        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setId(int $id): void
    {
        if ($this->id !== null) {
            throw new Exception();
        }

        $this->id = $id;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'stock' => $this->stock,
        ];
    }

    private function validateNotNegative(int|float $value, string $name): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$name}には0以上の値を指定してください。");
        }
    }
}
