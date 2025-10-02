<?php

declare(strict_types=1);

use App\Mapper\ReservationMapper;
use App\Model\Database;
use App\Model\Enum\TimeSlotStatus;
use App\Model\Reservation;
use App\Model\TimeSlot;
use App\Model\User;

require __DIR__ . '/../vendor/autoload.php';

$pdo = Database::getInstance()->getConnection();

// ---- Config ----
$userId = 1;          // 既存 users テーブルにある前提
$timeSlotId = 1;      // 既存 time_slots の available 行

// ---- Fetch user row (簡易) ----
$stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmtUser->execute([':id' => $userId]);
$rowUser = $stmtUser->fetch();
if (!$rowUser) {
    echo "User not found\n";
    exit(1);
}
$user = User::createFromDbRow($rowUser);

// ---- Fetch timeslot row ----
$stmtSlot = $pdo->prepare('SELECT * FROM time_slots WHERE id = :id');
$stmtSlot->execute([':id' => $timeSlotId]);
$rowSlot = $stmtSlot->fetch();
if (!$rowSlot) {
    echo "TimeSlot not found\n";
    exit(1);
}

// start_at 利用 (存在を想定)。DateTimeImmutable で生成
$startAt = new DateTimeImmutable($rowSlot['start_at']);
$status  = TimeSlotStatus::from($rowSlot['status']);
$timeSlot = new TimeSlot(startAt: $startAt, status: $status, id: (int)$rowSlot['id']);

// ---- Reservation 作成 & 保存 ----
$reservation = new Reservation($user, $timeSlot);
$mapper = new ReservationMapper($pdo);

try {
    $mapper->save($reservation);
    echo "Reservation saved. id={$reservation->getId()} timeSlotStatus={$timeSlot->getStatus()->name}\n";
} catch (LogicException $e) {
    echo "LogicException: {$e->getMessage()}\n";
} catch (Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
}

// 再度予約トライ (二重予約チェック)
try {
    $mapper->save(new Reservation($user, $timeSlot));
    echo "Unexpected: second reservation succeeded\n";
} catch (LogicException $e) {
    echo "Second attempt blocked as expected: {$e->getMessage()}\n";
}
