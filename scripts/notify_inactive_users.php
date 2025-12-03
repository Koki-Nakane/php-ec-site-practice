<?php

declare(strict_types=1);

use App\Service\MailSenderInterface;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "このスクリプトは CLI からのみ実行できます\n");
    exit(1);
}

$container = require __DIR__ . '/../config/container.php';

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** @var MailSenderInterface $mailer */
$mailer = $container->get(MailSenderInterface::class);

$options = parseOptions($argv);

$thresholdDate = (new DateTimeImmutable())
    ->sub(new DateInterval('P' . $options['days'] . 'D'));

$activityColumn = detectActivityColumn($pdo);
$activityExpression = $activityColumn === 'last_login_at'
    ? 'COALESCE(last_login_at, created_at)'
    : 'updated_at';

$inactiveUsers = fetchInactiveUsers(
    $pdo,
    $activityExpression,
    $thresholdDate,
    (bool) $options['include_admins']
);

if ($inactiveUsers === []) {
    fwrite(STDOUT, sprintf("基準日 %s 以降にログイン済みでないユーザーは見つかりませんでした。\n", $thresholdDate->format('Y-m-d')));
    exit(0);
}

$admins = fetchAdminRecipients($pdo);
if ($admins === []) {
    fwrite(STDERR, "送信先の管理者が存在しないため、メールを送信できません。\n");
    exit(1);
}

$subject = sprintf(
    '[Inactive Users] %d accounts require attention (%s)',
    count($inactiveUsers),
    (new DateTimeImmutable())->format('Y-m-d')
);

$body = buildEmailBody($inactiveUsers, $thresholdDate, $activityColumn, (int) $options['days']);

foreach ($admins as $admin) {
    if ($options['dry_run']) {
        printf("[dry-run] %s へメール送信をスキップしました。\n", $admin['email']);
        continue;
    }

    $mailer->send($admin['email'], $subject, $body, ['X-Mailer' => 'Problem46Batch/1.0']);
    printf("送信完了: %s (%s)\n", $admin['email'], $admin['name']);
}

printf("通知対象 %d 件 / 送信先 %d 件\n", count($inactiveUsers), count($admins));

exit(0);

/**
 * @param array<int, string> $argv
 * @return array{days:int,dry_run:bool,include_admins:bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'days' => 30,
        'dry_run' => false,
        'include_admins' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
            continue;
        }

        if ($arg === '--include-admins') {
            $options['include_admins'] = true;
            continue;
        }

        if (str_starts_with($arg, '--days=')) {
            $value = (int) substr($arg, 7);
            if ($value < 1) {
                throw new InvalidArgumentException('--days オプションは 1 以上の整数で指定してください。');
            }
            $options['days'] = $value;
        }
    }

    return $options;
}

function detectActivityColumn(PDO $pdo): string
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login_at' LIMIT 1"
    );
    $stmt->execute();

    return $stmt->fetchColumn() !== false ? 'last_login_at' : 'updated_at';
}

/**
 * @return array<int, array{id:int,name:string,email:string,last_activity_at:string}>
 */
function fetchInactiveUsers(PDO $pdo, string $activityExpression, DateTimeImmutable $threshold, bool $includeAdmins): array
{
    $conditions = ['deleted_at IS NULL'];

    if (!$includeAdmins) {
        $conditions[] = 'is_admin = 0';
    }

    $sql = sprintf(
        'SELECT id, name, email, %1$s AS last_activity_at
		 FROM users
		 WHERE %1$s IS NOT NULL
		   AND %1$s < :threshold
		   AND %2$s
		 ORDER BY %1$s ASC',
        $activityExpression,
        implode(' AND ', $conditions)
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':threshold' => $threshold->format('Y-m-d H:i:s')]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @return array<int, array{name:string,email:string}>
 */
function fetchAdminRecipients(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT name, email FROM users WHERE is_admin = 1 AND deleted_at IS NULL');
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param array<int, array{id:int,name:string,email:string,last_activity_at:string}> $inactiveUsers
 */
function buildEmailBody(array $inactiveUsers, DateTimeImmutable $thresholdDate, string $activityColumn, int $days): string
{
    $lines = [];
    $lines[] = '管理者各位';
    $lines[] = '';
    $lines[] = sprintf('以下のユーザーは最終ログインから %d 日以上経過しています。', $days);
    $lines[] = sprintf('基準日時: %s', $thresholdDate->format('Y-m-d H:i:s'));
    $lines[] = sprintf('判定カラム: %s', $activityColumn === 'last_login_at' ? 'users.last_login_at (NULL 時は created_at)' : 'users.updated_at');
    $lines[] = '';

    foreach ($inactiveUsers as $user) {
        $lines[] = sprintf(
            '- ID:%d / %s <%s> / 最終ログイン: %s',
            (int) $user['id'],
            $user['name'],
            $user['email'],
            $user['last_activity_at'] ?? '不明'
        );
    }

    $lines[] = '';
    $lines[] = '対応例: フォローアップメールの送信、アカウント整理などをご検討ください。';
    $lines[] = sprintf('自動送信: Problem46Batch (%s)', (new DateTimeImmutable())->format('Y-m-d H:i:s'));

    return implode("\n", $lines);
}
