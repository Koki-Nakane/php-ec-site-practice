<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Reservation; // 修正: 正しい名前空間
use PDO;

final class ReservationMapper
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * 永続化処理（未実装部分は後で追加）
     * TODO: TimeSlot に ID 等が追加されたら以下を具体化する
     */
    public function save(Reservation $reservation): void
    {
        try {
            $this->pdo->beginTransaction();

            // TODO: 1) time_slots を条件付き UPDATE (status='available')
            // TODO: 2) 影響行数 0 なら LogicException
            // TODO: 3) reservations へ INSERT (user_id, time_slot_id, created_at)
            // TODO: 4) lastInsertId を $reservation->setId()
            // TODO: 5) commit 後に $reservation->getTimeSlot()->book()

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e; // 再スロー
        }
    }
}
