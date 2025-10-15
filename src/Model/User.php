<?php

declare(strict_types=1);

namespace App\Model;

use Exception;
use InvalidArgumentException;

final class User
{
    private ?int $id;
    private string $hashedPassword;
    private string $name;

    public function __construct(
        string $name,
        private string $email,
        string $plainPassword,
        private string $address,
        ?int $id,
    ) {
        $this->setPassword($plainPassword);
        $this->id = $id;
        $this->setName($name);
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

    private function setName(string $name): void
    {
        $pattern = '/^[a-zA-Z0-9_]+$/';

        if (preg_match($pattern, $name) !== 1) {
            throw new InvalidArgumentException('Username must contain only ASCII letters, digits, or underscores.');
        }

        $this->name = $name;
    }

}
