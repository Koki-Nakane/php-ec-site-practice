<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Product
{
    private ?int $id;
    private string $name;
    private float $price;
    private string $description;
    private int $stock;
    private bool $isActive;
    private ?DateTimeImmutable $deletedAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        float $price,
        string $description,
        int $stock,
        bool $isActive = true,
        ?DateTimeImmutable $deletedAt = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?int $id = null
    ) {
        $this->name = '';
        $this->description = '';
        $this->stock = 0;
        $this->price = 0.0;
        $this->id = null;

        $this->rename($name);
        $this->changeDescription($description);
        $this->changePrice($price);
        $this->changeStock($stock);

        $this->isActive = $isActive;
        $this->deletedAt = $deletedAt;

        $now = new DateTimeImmutable();
        $this->createdAt = $createdAt ?? $now;
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        if ($this->deletedAt !== null && $this->deletedAt < $this->createdAt) {
            throw new InvalidArgumentException('削除日時は作成日時より前にはできません。');
        }

        if ($id !== null) {
            $this->setId($id);
        }
    }

    public static function createNew(
        string $name,
        float $price,
        string $description,
        int $stock,
        bool $isActive = true,
        ?DateTimeImmutable $clockNow = null
    ): self {
        $now = $clockNow ?? new DateTimeImmutable();

        return new self(
            $name,
            $price,
            $description,
            $stock,
            $isActive,
            null,
            $now,
            $now
        );
    }

    public static function rehydrate(array $row): self
    {
        $createdAt = self::createImmutable($row['created_at'] ?? null, 'created_at');
        $updatedAt = self::createImmutable($row['updated_at'] ?? null, 'updated_at');

        $deletedAt = null;
        if (array_key_exists('deleted_at', $row) && $row['deleted_at'] !== null && $row['deleted_at'] !== '') {
            $deletedAt = self::createImmutable($row['deleted_at'], 'deleted_at');
        }

        return new self(
            (string)($row['name'] ?? ''),
            (float)($row['price'] ?? 0),
            (string)($row['description'] ?? ''),
            (int)($row['stock'] ?? 0),
            isset($row['is_active']) ? (bool)$row['is_active'] : true,
            $deletedAt,
            $createdAt,
            $updatedAt,
            isset($row['id']) ? (int)$row['id'] : null
        );
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setId(int $id): void
    {
        if ($this->id !== null) {
            throw new DomainException('IDは一度しか設定できません。');
        }

        if ($id <= 0) {
            throw new InvalidArgumentException('IDには正の整数を指定してください。');
        }

        $this->id = $id;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('商品名は1文字以上で入力してください。');
        }

        if (mb_strlen($name, 'UTF-8') > 255) {
            throw new InvalidArgumentException('商品名は255文字以内で入力してください。');
        }

        $this->name = $name;
    }

    public function changePrice(float $price): void
    {
        $this->validateNotNegative($price, '価格');
        $this->price = round($price, 2);
    }

    public function changeDescription(string $description): void
    {
        $description = trim($description);

        if (mb_strlen($description, 'UTF-8') > 2000) {
            throw new InvalidArgumentException('説明文は2000文字以内で入力してください。');
        }

        $this->description = $description;
    }

    public function changeStock(int $stock): void
    {
        $this->validateNotNegative($stock, '在庫数');
        $this->stock = $stock;
    }

    public function adjustStock(int $delta): void
    {
        $newStock = $this->stock + $delta;

        if ($newStock < 0) {
            throw new DomainException('在庫数を負の値にすることはできません。');
        }

        $this->stock = $newStock;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function markDeleted(DateTimeImmutable $deletedAt): void
    {
        if ($this->isDeleted()) {
            throw new DomainException('すでに削除済みの商品です。');
        }

        if ($deletedAt < $this->createdAt) {
            throw new InvalidArgumentException('削除日時は作成日時より後にしてください。');
        }

        $this->deletedAt = $deletedAt;
    }

    public function restore(): void
    {
        if (!$this->isDeleted()) {
            throw new DomainException('削除されていない商品は復元できません。');
        }

        $this->deletedAt = null;
    }

    public function touch(DateTimeImmutable $updatedAt): void
    {
        if ($updatedAt < $this->createdAt) {
            throw new InvalidArgumentException('更新日時は作成日時より前にできません。');
        }

        $this->updatedAt = $updatedAt;
    }

    public function setPrice(float $price): void
    {
        $this->changePrice($price);
    }

    public function setStock(int $stock): void
    {
        $this->changeStock($stock);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'stock' => $this->stock,
            'is_active' => $this->isActive,
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public function toPublicPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'stock' => $this->stock,
        ];
    }

    private static function createImmutable(?string $value, string $field): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException(sprintf('%s が指定されていません。', $field));
        }

        return new DateTimeImmutable($value);
    }

    private function validateNotNegative(int|float $value, string $name): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$name}には0以上の値を指定してください。");
        }
    }
}
