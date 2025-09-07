<?php

declare(strict_types=1);

namespace App\Model\Enum;

enum TimeSlotStatus: string
{
    case Available = 'available';
    case Booked = 'booked';
}
