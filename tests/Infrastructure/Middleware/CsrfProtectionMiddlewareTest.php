<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\Middleware\CsrfProtectionMiddleware;
use App\Service\CsrfTokenManager;
use PHPUnit\Framework\TestCase;

final class CsrfProtectionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $_SESSION = [];
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function testSafeMethodsBypassProtection(): void
    {
        $middleware = new CsrfProtectionMiddleware(new CsrfTokenManager(), 'api');
        $request = new Request(method: 'GET', path: '/posts');
        $called = false;

        $response = $middleware->process($request, function (Request $req) use (&$called): Response {
            $called = true;
            return new Response(200, 'ok');
        });

        $this->assertTrue($called);
        $this->assertSame(200, $response->status);
    }

    public function testValidBodyTokenAllowsRequest(): void
    {
        $tokens = new CsrfTokenManager();
        $token = $tokens->issue('form_token');
        $middleware = new CsrfProtectionMiddleware($tokens, 'web');
        $request = new Request(method: 'POST', path: '/add_to_cart', body: ['_token' => $token]);

        $response = $middleware->process($request, fn (Request $req): Response => new Response(204));

        $this->assertSame(204, $response->status);
        $this->assertFalse($tokens->consume($token), 'トークンは一度のリクエストで消費される');
    }

    public function testMissingTokenReturnsErrorForApi(): void
    {
        $middleware = new CsrfProtectionMiddleware(new CsrfTokenManager(), 'api');
        $request = new Request(method: 'POST', path: '/posts');

        $response = $middleware->process($request, fn (Request $req): Response => new Response(200));

        $this->assertSame(419, $response->status);
        $this->assertStringContainsString('invalid_csrf_token', $response->body);
    }

    public function testWebChannelRedirectsAndSetsFlash(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTP_REFERER'] = 'http://localhost/admin/products';

        $middleware = new CsrfProtectionMiddleware(new CsrfTokenManager(), 'web');
        $request = new Request(method: 'POST', path: '/add_to_cart');

        $response = $middleware->process($request, fn (Request $req): Response => new Response(200));

        $this->assertSame(303, $response->status);
        $this->assertSame('/admin/products', $response->headers['Location'] ?? null);
        $this->assertNotEmpty($_SESSION['flash'] ?? []);
    }
}
