<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use DateInterval;
use DateTimeImmutable;

final class PasswordResetService
{
    private DateInterval $ttl;

    public function __construct(
        private \PDO $pdo,
        private MailSenderInterface $mailer,
        ?DateInterval $ttl = null
    ) {
        $this->ttl = $ttl ?? new DateInterval('PT1H');
    }

    public function request(User $user, string $resetUrlBase): void
    {
        $this->cleanupExpired();

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = (new DateTimeImmutable())->add($this->ttl);

        $userId = $user->getId();
        if ($userId === null) {
            throw new \LogicException('User must be persisted before issuing reset tokens.');
        }

        $this->deleteExistingForUser($userId);

        $stmt = $this->pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)');
        $stmt->execute([
            ':user_id' => $userId,
            ':token' => $tokenHash,
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        $resetLink = $this->buildResetLink($resetUrlBase, $token);
        $subject = 'パスワード再設定のご案内';
        $body = "以下のリンクからパスワードの再設定を行ってください。リンクの有効期限は1時間です。\n\n" .
            $resetLink . "\n\n" .
            'このメールに覚えがない場合は破棄してください。';

        $this->mailer->send($user->getEmail(), $subject, $body);
    }

    /**
     * @return array{id:int,user_id:int,expires_at:DateTimeImmutable}|null
     */
    public function findValidToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM password_resets WHERE token = :token LIMIT 1');
        $stmt->execute([':token' => $this->hashToken($token)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $expiresAt = new DateTimeImmutable((string) $row['expires_at']);
        $now = new DateTimeImmutable();
        if ($expiresAt < $now) {
            return null;
        }

        if (!empty($row['used_at'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'expires_at' => $expiresAt,
        ];
    }

    public function markAsUsed(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE password_resets SET used_at = :used_at WHERE id = :id');
        $stmt->execute([
            ':used_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    private function buildResetLink(string $baseUrl, string $token): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        return sprintf('%s?token=%s', $baseUrl, urlencode($token));
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function deleteExistingForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
    }

    private function cleanupExpired(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_resets WHERE expires_at < :now OR used_at IS NOT NULL');
        $stmt->execute([':now' => (new DateTimeImmutable())->format('Y-m-d H:i:s')]);
    }
}
