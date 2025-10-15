<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\TimeSlotStatus;
use DateTimeImmutable;

class TimeSlot
{
    private ?int $id;
    private DateTimeImmutable $startAt;
    private TimeSlotStatus $status;

    public function __construct(
        DateTimeImmutable $startAt,
        TimeSlotStatus $status = TimeSlotStatus::Available,
        ?int $id = null,
    ) {
        $this->id = $id;
        $this->startAt = $startAt;
        $this->status = $status;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getStartAt(): DateTimeImmutable
    {
        return $this->startAt;
    }

    public function getStatus(): TimeSlotStatus
    {
        return $this->status;
    }

    public function isAvailable(): bool
    {
        return $this->status === TimeSlotStatus::Available;
    }

    public function book(): void
    {
        if ($this->status !== TimeSlotStatus::Available) {
            throw new \LogicException('TimeSlot cannot be booked from current status: '.$this->status->name);
        }
        $this->status = TimeSlotStatus::Booked;
    }
}
