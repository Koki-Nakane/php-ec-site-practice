<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;

final class Reservation
{
    private ?int $id;
    private DateTime $createdAt;

    public function __construct(
        private User $user,
        private TimeSlot $timeSlot,
        ?int $id = null,
    ) {
        $this->id = $id;
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // Mapper 用
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTimeSlot(): TimeSlot
    {
        return $this->timeSlot;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
