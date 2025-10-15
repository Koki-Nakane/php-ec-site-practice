<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;
use App\Traits\Logger;

/**
 * 最外層で例外を捕捉して、Web/APIに応じたレスポンスを返す。
 * 想定外の例外のみここで処理。
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    use Logger;

    public function __construct(private bool $apiMode = false)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (\Throwable $e) {
            $this->log(sprintf('UNHANDLED %s: %s', $e::class, $e->getMessage()));
            if ($this->apiMode) {
                return Response::json(['error' => 'internal_server_error'], 500);
            }
            // 簡易な500ページ
            return new Response(
                status: 500,
                body: '<h1>500 Internal Server Error</h1>'
            );
        }
    }
}
