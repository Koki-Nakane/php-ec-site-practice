<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Controller\AuthController;
use App\Http\Request;
use App\Http\Response;

final class AdminAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthController $auth)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->auth->isAuthenticated()) {
            return Response::redirect('/login', 303);
        }

        if (!$this->auth->isAdmin()) {
            return new Response(
                status: 403,
                body: '<h1>403 Forbidden</h1><p>管理者権限が必要です。</p>',
                headers: ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        return $next($request);
    }
}
