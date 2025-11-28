<?php

declare(strict_types=1);

use App\Mapper\UserMapper;
use App\Model\Database;
use App\Model\User;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the CLI.\n");
    exit(1);
}

$options = getopt('', [
    'name::',
    'email::',
    'password::',
    'address::',
    'admin::',
    'force::',
    'help::',
]);

if (isset($options['help'])) {
    fwrite(
        STDOUT,
        <<<'USAGE'
Usage: php scripts/create_test_user.php [--name=demo_user] [--email=demo@example.com] [--password=Password123!] [--address="Tokyo"] [--admin=0|1] [--force=1]
USAGE
    );
    exit(0);
}

$name = isset($options['name']) ? (string) $options['name'] : 'test_user';
$email = isset($options['email']) ? (string) $options['email'] : 'test-user@example.com';
$password = isset($options['password']) ? (string) $options['password'] : 'TestPass123!';
$address = isset($options['address']) ? (string) $options['address'] : '東京都港区テスト1-2-3';
$admin = filter_var($options['admin'] ?? '0', FILTER_VALIDATE_BOOLEAN);
$force = filter_var($options['force'] ?? '0', FILTER_VALIDATE_BOOLEAN);

if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
    fwrite(STDERR, "--name には英数字とアンダースコアのみ使用できます。\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "--email の形式が不正です。\n");
    exit(1);
}

if (mb_strlen($password, 'UTF-8') < 8) {
    fwrite(STDERR, "--password は8文字以上で指定してください。\n");
    exit(1);
}

$pdo = Database::getInstance()->getConnection();
$userMapper = new UserMapper($pdo);

$existing = $userMapper->findByEmail($email);
if ($existing !== null && !$force) {
    fwrite(STDERR, sprintf("%s は既に存在します。更新するには --force=1 を付けてください。\n", $email));
    exit(1);
}

$user = new User(
    $name,
    $email,
    $password,
    $address,
    $existing?->getId(),
    $admin,
    $existing?->getDeletedAt()
);

$userMapper->save($user);

$action = $existing === null ? 'inserted' : 'updated';
fwrite(STDOUT, sprintf("[users] %s: %s (password: %s)\n", $action, $email, $password));
