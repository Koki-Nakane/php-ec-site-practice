<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;
use App\Service\CsrfTokenManager;

final class CsrfProtectionMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private CsrfTokenManager $tokens,
        private string $channel,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if (in_array($request->method, self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $token = $this->extractToken($request);
        if ($token === null || !$this->tokens->consume($token)) {
            return $this->handleFailure();
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        foreach ($request->headers as $name => $value) {
            if (strcasecmp($name, 'X-CSRF-Token') === 0) {
                $trimmed = trim((string) $value);
                return $trimmed === '' ? null : $trimmed;
            }
        }

        $bodyToken = $request->body['_token'] ?? null;
        if (!is_string($bodyToken)) {
            return null;
        }

        $trimmed = trim($bodyToken);
        return $trimmed === '' ? null : $trimmed;
    }

    private function handleFailure(): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $message = 'フォームの有効期限が切れました。もう一度お試しください。';

        if ($this->channel === 'api') {
            return Response::json(['error' => 'invalid_csrf_token'], 419);
        }

        if ($this->channel === 'admin') {
            $_SESSION['admin_flash'][] = ['type' => 'error', 'message' => $message];
        } else {
            $_SESSION['flash'][] = $message;
            $_SESSION['error_message'] = $message;
        }

        $redirect = $this->determineRedirectTarget();
        return Response::redirect($redirect);
    }

    private function determineRedirectTarget(): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        if ($referer === null) {
            return '/';
        }

        if (str_starts_with($referer, '/')) {
            return $referer;
        }

        $parsed = parse_url($referer);
        if ($parsed === false) {
            return '/';
        }

        $host = $_SERVER['HTTP_HOST'] ?? null;
        if ($host !== null && isset($parsed['host']) && strcasecmp($parsed['host'], $host) === 0) {
            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            return $path . $query;
        }

        return '/';
    }
}
