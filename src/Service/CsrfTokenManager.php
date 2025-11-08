<?php

declare(strict_types=1);

namespace App\Service;

final class CsrfTokenManager
{
    private const SESSION_KEY = '_csrf_tokens';

    public function issue(string $id): string
    {
        $this->ensureSessionStarted();

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY][$id] = $token;

        return $token;
    }

    public function validate(string $id, ?string $submittedToken): bool
    {
        $this->ensureSessionStarted();

        $storedToken = $_SESSION[self::SESSION_KEY][$id] ?? null;
        if (!is_string($storedToken) || !is_string($submittedToken)) {
            return false;
        }

        $isValid = hash_equals($storedToken, $submittedToken);
        if ($isValid) {
            unset($_SESSION[self::SESSION_KEY][$id]);
        }

        return $isValid;
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
