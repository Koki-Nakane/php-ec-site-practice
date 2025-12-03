<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Contracts\EventDispatcherInterface;
use App\Model\User;
use App\Traits\Logger;
use DateTimeImmutable;

final class UserMapper
{
    use Logger;

    private \PDO $pdo;
    private ?EventDispatcherInterface $events;

    public function __construct(\PDO $pdo, ?EventDispatcherInterface $events = null)
    {
        $this->pdo = $pdo;
        $this->events = $events;
    }

    public function find(int $id): ?User
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return User::createFromDbRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $sql = 'SELECT * FROM users WHERE email = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return User::createFromDbRow($row);
    }

    /**
     * @return User[]
     */
    public function listForAdmin(?bool $onlyDeleted = null, int $limit = 100, int $offset = 0): array
    {
        $conditions = [];
        if ($onlyDeleted === true) {
            $conditions[] = 'deleted_at IS NOT NULL';
        } elseif ($onlyDeleted === false) {
            $conditions[] = 'deleted_at IS NULL';
        }

        $sql = 'SELECT * FROM users';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(User $user): void
    {
        // getId() は null か 正の整数想定。0 判定は不要なので取り除き、変数に保持して判定回数も減らす。
        $id = $user->getId();
        if ($id === null) {
            $this->insert($user);
            return;
        }

        $this->update($user);

    }

    private function insert(User $user): void
    {
        $sql = 'INSERT INTO users (name, email, password, address, is_admin, deleted_at) VALUES (:name, :email, :password, :address, :is_admin, :deleted_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $user->getName(),
            ':email' => $user->getEmail(),
            ':password' => $user->getHashedPassword(),
            ':address' => $user->getAddress(),
            ':is_admin' => $user->isAdmin() ? 1 : 0,
            ':deleted_at' => $this->formatNullableDate($user->getDeletedAt()),
        ]);

        $id = $this->pdo->lastInsertId();
        $user->setId((int)$id);

        $this->log("User created: ID = {$user->getId()}, Name = {$user->getName()}, Email = {$user->getEmail()}");

        // Emit domain event (if dispatcher is available)
        if ($this->events) {
            $this->events->dispatch('user.created', [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
            ]);
        }
    }

    private function update(User $user): void
    {
        $sql = 'UPDATE users SET name = :name, email = :email, password = :password, address = :address, is_admin = :is_admin, deleted_at = :deleted_at WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':name' => $user->getName(),
            ':email' => $user->getEmail(),
            ':password' => $user->getHashedPassword(),
            ':address' => $user->getAddress(),
            ':is_admin' => $user->isAdmin() ? 1 : 0,
            ':deleted_at' => $this->formatNullableDate($user->getDeletedAt()),
            ':id' => $user->getId(),
        ]);

        $this->log("User updated: ID = {$user->getId()}, Name = {$user->getName()}");
    }

    public function updateLastLogin(int $userId, DateTimeImmutable $when): void
    {
        $sql = 'UPDATE users SET last_login_at = :last_login_at WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':last_login_at' => $when->format('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);
    }

    public function delete(User $user): void
    {
        $id = $user->getId();
        if ($id === null) {
            return;
        }

        $this->markDeleted($id, new DateTimeImmutable());
    }

    public function markDeleted(int $id, DateTimeImmutable $when): ?User
    {
        $user = $this->find($id);
        if ($user === null) {
            return null;
        }

        if ($user->isDeleted()) {
            return $user;
        }

        $user->markDeleted($when);
        $this->update($user);
        $this->log("User soft-deleted: ID = {$user->getId()}, Email = {$user->getEmail()}");

        return $user;
    }

    public function restore(int $id): ?User
    {
        $user = $this->find($id);
        if ($user === null) {
            return null;
        }

        if (!$user->isDeleted()) {
            return $user;
        }

        $user->restore();
        $this->update($user);
        $this->log("User restored: ID = {$user->getId()}, Email = {$user->getEmail()}");

        return $user;
    }

    private function formatNullableDate(?DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    private function hydrate(array $row): User
    {
        return User::createFromDbRow($row);
    }

}
