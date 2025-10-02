<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Controller\AuthController;
use App\Http\Request;
use App\Http\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthController $auth,
        private string $loginPath = '/login.php',
        private bool $apiMode = false,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->auth->isAuthenticated()) {
            if ($this->apiMode) {
                return Response::json(['error' => 'unauthorized'], 401);
            }

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['flash'][] = 'ログインが必要です。';
            $redirectTo = $this->loginPath . '?redirect=' . urlencode($request->path);
            return Response::redirect($redirectTo, 303);
        }

        return $next($request);
    }
}
