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

    public function __construct(
        User $user,
        Cart $cart,
        ?int $id = null
    ) {
        $this->user = $user;
        $this->cartItems = $cart->getItems();
        $this->totalPrice = $cart->getTotalPrice();
        $this->date = new DateTime();

        $this->id = $id;
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
}
