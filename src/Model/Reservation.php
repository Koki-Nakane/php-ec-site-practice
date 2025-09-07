<?php

declare(strict_types=1);

namespace App\Model;

final class Reservation
{
    private ?int $id;

    public function __construct(
        private User $user,
        private TimeSlot $timeSlot,
        ?int $id = null
    ) {
        $this->id = $id;
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
}
