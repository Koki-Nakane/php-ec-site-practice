<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;

final class Order
{
    private ?int $id = null;
    private User $user;
    private array $cartItems;
    private float $totalPrice;
    private DateTime $date;
    private string $shippingAddress;

    public function __construct(
        User $user,
        Cart $cart,
        ?int $id = null,
        ?string $shippingAddress = null,
        ?DateTime $createdAt = null
    ) {
        $this->user = $user;
        $this->cartItems = $cart->getItems();
        $this->totalPrice = $cart->getTotalPrice();
        $this->date = $createdAt ?? new DateTime();
        $this->shippingAddress = $shippingAddress ?? $user->getAddress();

        $this->id = $id;
    }

    public static function fromSnapshot(
        User $user,
        array $items,
        float $totalPrice,
        string $shippingAddress,
        string $createdAt,
        int $id
    ): self {
        $order = new self(
            $user,
            new Cart(),
            $id,
            $shippingAddress,
            new DateTime($createdAt)
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
        $this->id = $id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function getShippingAddress(): string
    {
        return $this->shippingAddress;
    }
}
