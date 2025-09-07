<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mapper\ReservationMapper;
use App\Model\Reservation;
use App\Model\TimeSlot;
use App\Model\User;

final class ReservationController
{
    public function __construct(
        private ReservationMapper $reservationMapper
    ) {
    }

    public function makeReservation(User $user, TimeSlot $timeSlot): Reservation
    {
        // 事前の早期チェック（最終確定は Mapper 側）
        if (!$timeSlot->isAvailable()) {
            throw new \LogicException('この時間枠は既に予約されています。');
        }

        $reservation = new Reservation($user, $timeSlot);

        // save 内で:
        // 1) トランザクション開始
        // 2) TimeSlot 行ロック & status 再確認
        // 3) reservations INSERT
        // 4) time_slots UPDATE (booked)
        // 5) コミット後に $timeSlot->book() でメモリ状態同期（Mapper内 or 戻ってからどちらか統一）
        $this->reservationMapper->save($reservation);

        return $reservation;
    }
}
