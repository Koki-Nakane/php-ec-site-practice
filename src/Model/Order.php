<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\OrderStatus;
use DateTimeImmutable;
use DomainException;
use Exception;
use InvalidArgumentException;

final class Order
{
    private ?int $id;
    private User $user;
    /**
     * @var array<int,array{product: Product, quantity: int, price?: float}>
     */
    private array $cartItems;
    private float $totalPrice;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private string $shippingAddress;
    private int $status;
    private ?DateTimeImmutable $deletedAt;

    public function __construct(
        User $user,
        Cart $cart,
        ?int $id = null,
        ?string $shippingAddress = null,
        ?DateTimeImmutable $createdAt = null,
        int $status = OrderStatus::PENDING,
        ?DateTimeImmutable $updatedAt = null,
        ?DateTimeImmutable $deletedAt = null,
    ) {
        $this->user = $user;
        $this->cartItems = $cart->getItems();
        $this->totalPrice = $cart->getTotalPrice();
        $this->id = $id;

        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->shippingAddress = $this->normalizeAddress($shippingAddress ?? $user->getAddress());

        $this->status = $this->assertValidStatus($status);
        $this->deletedAt = $deletedAt;
    }

    /**
     * @param array<int,array{product: Product, quantity: int, price?: float}> $items
     */
    public static function fromSnapshot(
        User $user,
        array $items,
        float $totalPrice,
        string $shippingAddress,
        string $createdAt,
        int $status,
        string $updatedAt,
        ?string $deletedAt,
        int $id
    ): self {
        $order = new self(
            $user,
            new Cart(),
            $id,
            $shippingAddress,
            new DateTimeImmutable($createdAt),
            $status,
            new DateTimeImmutable($updatedAt),
            $deletedAt !== null ? new DateTimeImmutable($deletedAt) : null,
        );

        $order->cartItems = $items;
        $order->totalPrice = $totalPrice;

        return $order;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        if ($this->id !== null) {
            throw new Exception('IDは一度設定した後に変更できません。');
        }

        if ($id <= 0) {
            throw new InvalidArgumentException('IDには正の整数を指定してください。');
        }

        $this->id = $id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return array<int,array{product: Product, quantity: int, price?: float}>
     */
    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getShippingAddress(): string
    {
        return $this->shippingAddress;
    }

    public function changeShippingAddress(string $address): void
    {
        $this->shippingAddress = $this->normalizeAddress($address);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $this->assertValidStatus($status);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function markDeleted(DateTimeImmutable $when): void
    {
        if ($this->isDeleted()) {
            throw new DomainException('注文はすでに削除済みです。');
        }

        $this->deletedAt = $when;
    }

    public function restore(): void
    {
        if (!$this->isDeleted()) {
            throw new DomainException('削除されていない注文は復元できません。');
        }

        $this->deletedAt = null;
    }

    private function normalizeAddress(string $address): string
    {
        $address = trim($address);

        if (mb_strlen($address, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('配送先住所は1000文字以内で入力してください。');
        }

        return $address;
    }

    private function assertValidStatus(int $status): int
    {
        if (!OrderStatus::isValid($status)) {
            throw new InvalidArgumentException('注文ステータスが不正です。');
        }

        return $status;
    }
}
