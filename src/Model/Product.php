<?php

declare(strict_types=1);

namespace App\Model;

use InvalidArgumentException;
use Exception;

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

    public function getId(): int
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
        if ($this->id !== 0) {
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
}
