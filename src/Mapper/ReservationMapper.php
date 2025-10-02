<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Enum\TimeSlotStatus;
use App\Model\Reservation;
use LogicException;
use PDO;
use RuntimeException;

final class ReservationMapper
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Reservation を永続化し、TimeSlot を booked に更新する。
     * 楽観ロック: status='available' 条件付き UPDATE により競合を検出。
     */
    public function save(Reservation $reservation): void
    {
        try {
            $this->pdo->beginTransaction();
            // 1) TimeSlot 状態更新 (楽観ロック) + ID 取得
            $timeSlotId = $this->lockAndBookTimeSlot($reservation);

            // 2) Reservation 挿入 & ID 付与
            $this->insertReservation($reservation, $timeSlotId);

            // 3) コミット後にドメインオブジェクト同期
            $this->pdo->commit();
            $reservation->getTimeSlot()->book();

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 条件付き UPDATE で TimeSlot を booked に変更。競合なら例外。
     * @return int TimeSlot ID
     */
    private function lockAndBookTimeSlot(Reservation $reservation): int
    {
        $timeSlot = $reservation->getTimeSlot();
        $timeSlotId = $timeSlot->getId();
        if ($timeSlotId === null) {
            throw new LogicException('TimeSlot id is required before saving reservation.');
        }

        $sql = 'UPDATE time_slots SET status = :newStatus WHERE id = :id AND status = :expectedStatus';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':newStatus'      => TimeSlotStatus::Booked->value,
            ':expectedStatus' => TimeSlotStatus::Available->value,
            ':id'             => $timeSlotId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new LogicException('Time slot already booked.');
        }

        return $timeSlotId;
    }

    /**
     * reservations 行を挿入し Reservation に ID を設定。
     */
    private function insertReservation(Reservation $reservation, int $timeSlotId): void
    {
        $sql = 'INSERT INTO reservations (user_id, time_slot_id, created_at) VALUES (:userId, :slotId, :createdAt)';
        $stmt = $this->pdo->prepare($sql);
        $createdAt = $reservation->getCreatedAt()->format('Y-m-d H:i:s');
        $stmt->execute([
            ':userId'    => $reservation->getUser()->getId(),
            ':slotId'    => $timeSlotId,
            ':createdAt' => $createdAt,
        ]);

        $lastId = (int) $this->pdo->lastInsertId();
        if ($lastId <= 0) {
            throw new RuntimeException('Failed to obtain reservation id.');
        }
        $reservation->setId($lastId);
    }
}
