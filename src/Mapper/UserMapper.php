<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Database;
use App\Model\User;
use PDO;
use App\Traits\Logger;

final class UserMapper
{
    use Logger;
    
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    

    public function find(int $id): ?User
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row){
            return null;
        }
        
        return User::createFromDbRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row){
            return null;
        }
        
        return User::createFromDbRow($row);
    }

    public function save(User $user): void
    {
        if ($user->getId() === null || $user->getId() === 0) {
            $this->insert($user);
        } else {
            $this->update($user);
        }

    }

    private function insert(User $user): void
    {
        $sql = "INSERT INTO users (name, email, password, address) VALUES (:name, :email, :password, :address)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $user->getName(),
            ':email' => $user->getEmail(),
            ':password' => $user->getHashedPassword(),
            ':address' => $user->getAddress(),
        ]);

        $id = $this->pdo->lastInsertId();
        $user->setId((int)$id);

        $this->log("User created: ID = {$user->getId()}, Name = {$user->getName()}, Email = {$user->getEmail()}");
    }

    private function update(User $user): void
    {
        $sql = "UPDATE users SET name = :name, email = :email, password = :password, address = :address WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':name' => $user->getName(),
            ':email' => $user->getEmail(),
            ':password' => $user->getHashedPassword(),
            ':address' => $user->getAddress(),
            ':id' => $user->getId(),
        ]);

        $this->log("User updated: ID = {$user->getId()}, Name = {$user->getName()}");
    }

    public function delete(User $user): void
    {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user->getId()]);

        $this->log("User deleted: ID = {$user->getId()}, Email = {$user->getEmail()}");
    }

}
