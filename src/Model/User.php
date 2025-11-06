<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use DomainException;
use Exception;
use InvalidArgumentException;

final class User
{
    private ?int $id;
    private string $hashedPassword;
    private string $name;
    private bool $isAdmin;
    private ?DateTimeImmutable $deletedAt;

    public function __construct(
        string $name,
        private string $email,
        string $plainPassword,
        private string $address,
        ?int $id,
        bool $isAdmin = false,
        ?DateTimeImmutable $deletedAt = null,
    ) {
        $this->setPassword($plainPassword);
        $this->id = $id;
        $this->setName($name);
        $this->isAdmin = $isAdmin;
        $this->deletedAt = $deletedAt;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->hashedPassword);
    }

    public static function createFromDbRow(array $row): User
    {
        $deletedAt = null;
        if (!empty($row['deleted_at'])) {
            $deletedAt = new DateTimeImmutable((string)$row['deleted_at']);
        }

        $isAdmin = isset($row['is_admin']) ? ((int)$row['is_admin'] === 1) : false;

        $user = new self(
            $row['name'],
            $row['email'],
            'dummypassword',
            $row['address'],
            (int)$row['id'],
            $isAdmin,
            $deletedAt,
        );

        $user->setHashedPassword($row['password']);

        return $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getHashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function promoteToAdmin(): void
    {
        $this->isAdmin = true;
    }

    public function demoteFromAdmin(): void
    {
        $this->isAdmin = false;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function markDeleted(DateTimeImmutable $deletedAt): void
    {
        if ($this->isDeleted()) {
            throw new DomainException('ユーザーはすでに削除済みです。');
        }

        $this->deletedAt = $deletedAt;
    }

    public function restore(): void
    {
        if (!$this->isDeleted()) {
            throw new DomainException('削除されていないユーザーは復元できません。');
        }

        $this->deletedAt = null;
    }

    public function setId(int $id): void
    {
        if ($this->id !== null) {
            throw new Exception();
        }

        if ($id <= 0) {
            throw new Exception();
        }

        $this->id = $id;
    }

    private function setPassword(string $plainPassword): void
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->hashedPassword = $hashedPassword;
    }

    private function setHashedPassword(string $hashedPassword): void
    {
        $this->hashedPassword = $hashedPassword;
    }

    private function setName(string $name): void
    {
        $pattern = '/^[a-zA-Z0-9_]+$/';

        if (preg_match($pattern, $name) !== 1) {
            throw new InvalidArgumentException('Username must contain only ASCII letters, digits, or underscores.');
        }

        $this->name = $name;
    }

}
