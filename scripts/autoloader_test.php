<?php

declare(strict_types=1);

// Use the custom autoloader only (no Composer here on purpose)
require __DIR__ . '/../my_autoloader.php';

function expect(bool $cond, string $message): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// 1) Class load test: User
$u = new \App\Model\User(
    name: 'Taro',
    email: 'taro@example.com',
    plainPassword: 'secret',
    address: 'Tokyo',
    id: null,
);
expect($u->getName() === 'Taro', 'User::getName returns expected');
expect($u->verifyPassword('secret') === true, 'User::verifyPassword works');

// 2) Enum + class load test: TimeSlot and TimeSlotStatus
$start = new DateTimeImmutable('2025-01-01 09:00:00');
$ts = new \App\Model\TimeSlot(id: null, startAt: $start);
expect($ts->isAvailable() === true, 'TimeSlot default status is Available');

// Explicit enum usage should autoload enum as well
$status = \App\Model\Enum\TimeSlotStatus::Available;
expect($status->value === 'available', 'TimeSlotStatus enum autoloaded');

// Book it and verify state change
$ts->book();
expect($ts->getStatus() === \App\Model\Enum\TimeSlotStatus::Booked, 'TimeSlot booked -> Booked');

echo "All autoloader tests passed.\n";
