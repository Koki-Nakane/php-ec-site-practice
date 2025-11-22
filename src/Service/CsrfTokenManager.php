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
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

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

    public function consume(?string $submittedToken): bool
    {
        $this->ensureSessionStarted();

        if (!is_string($submittedToken) || $submittedToken === '') {
            return false;
        }

        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($tokens)) {
            return false;
        }

        foreach ($tokens as $id => $storedToken) {
            if (!is_string($storedToken)) {
                continue;
            }

            if (hash_equals($storedToken, $submittedToken)) {
                unset($_SESSION[self::SESSION_KEY][$id]);
                return true;
            }
        }

        return false;
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
