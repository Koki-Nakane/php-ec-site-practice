<?php

declare(strict_types=1);

namespace App\Model;

use Exception;

final class User
{
    private ?int $id;
    private string $hashedPassword;

    public function __construct(
        private string $name,
        private string $email,
        string $plainPassword,
        private string $address,
        ?int $id,
    ) {
        $this->setPassword($plainPassword);
        $this->id = $id;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->hashedPassword);
    }

    public static function createFromDbRow(array $row): User
    {
        $user = new self(
            $row['name'],
            $row['email'],
            'dummypassword',
            $row['address'],
            (int)$row['id']
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

    private function setPassword($plainPassword): void
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->hashedPassword = $hashedPassword;
    }

    private function setHashedPassword(string $hashedPassword): void
    {
        $this->hashedPassword = $hashedPassword;
    }

}
