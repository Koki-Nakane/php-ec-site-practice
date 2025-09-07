<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\TimeSlotStatus;

class TimeSlot
{
    private TimeSlotStatus $status;

    public function __construct()
    {
        $this->status = TimeSlotStatus::Available;
    }

    public function book(): void
    {
        $this->status = TimeSlotStatus::Booked;
    }

    public function isAvailable(): bool
    {
        return $this->status === TimeSlotStatus::Available;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }
}
