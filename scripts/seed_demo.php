<?php

declare(strict_types=1);

use App\Mapper\UserMapper;
use App\Model\Database;
use App\Model\User;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "このスクリプトは CLI からのみ実行できます\n");
    exit(1);
}

$pdo = Database::getInstance()->getConnection();
$userMapper = new UserMapper($pdo);

$users = [
    [
        'name' => 'demo_taro',
        'email' => 'demo@example.com',
        'password' => 'Password123',
        'address' => '東京都港区1-2-3 デモマンション101',
    ],
];

foreach ($users as $seed) {
    $existing = $userMapper->findByEmail($seed['email']);
    $user = new User(
        $seed['name'],
        $seed['email'],
        $seed['password'],
        $seed['address'],
        $existing?->getId()
    );

    $userMapper->save($user);

    if ($existing === null) {
        fwrite(STDOUT, "[users] inserted: {$seed['email']}\n");
    } else {
        fwrite(STDOUT, "[users] updated: {$seed['email']}\n");
    }
}

fwrite(STDOUT, "シード処理が完了しました。\n");
