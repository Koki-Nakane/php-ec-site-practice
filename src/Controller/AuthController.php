<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mapper\UserMapper;

final class AuthController
{
    private UserMapper $userMapper;

    public function __construct(
        UserMapper $userMapper
    ) {
        $this->userMapper = $userMapper;
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->userMapper->findByEmail($email);

        if ($user === null) {
            return false;
        }

        if (password_verify($password, $user->getHashedPassword()) ) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->getId();
            return true;
        }

        return false;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }
}

