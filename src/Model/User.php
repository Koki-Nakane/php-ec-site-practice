<?php

declare(strict_types=1);

namespace App\Model;

use Exception;

final class User
{
    private string $hashedPassword;

    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        string $plainPassword,
        private string $address,
    ) {
        $this->setPassword($plainPassword);
    }

    public function setPassword($plainPassword): void
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->hashedPassword = $hashedPassword;
    }

    public function verifyPassword($plainPassword): bool
    {
        return password_verify($plainPassword, $this->hashedPassword);
    }

    public static function createFromDbRow(array $row): User
    {
        $user = new self(
            (int)$row['id'],
            $row['name'],
            $row['email'],
            'dummypassword',
            $row['address']
        );

        $user->setHashedPassword($row['password']);

        return $user;
    }

    public function setHashedPassword(string $hashedPassword): void
    {
        $this->hashedPassword = $hashedPassword;
    }

    public function getId(): int
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

    public function setId(int $id): void
    {
        if ($this->id !== 0) {
            throw new Exception();
        }

        $this->id = $id;
    }

}
