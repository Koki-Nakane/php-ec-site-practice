<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;
use App\Traits\Logger;

final class LoggingMiddleware implements MiddlewareInterface
{
    use Logger;

    public function process(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $rid = bin2hex(random_bytes(8));

        $uid = $_SESSION['user_id'] ?? '-';
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '-';
        $ua  = $request->headers['User-Agent'] ?? '-';
        $this->log(sprintf('[%s] START %s %s uid=%s ip=%s ua=%s', $rid, $request->method, $request->path, (string)$uid, (string)$ip, (string)$ua));

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->log(sprintf('[%s] EXCEPTION %s: %s', $rid, $e::class, $e->getMessage()));
            throw $e; // 想定外は上位に投げる
        } finally {
            $dur = (int) ((microtime(true) - $start) * 1000);
            $status = isset($response) ? $response->status : 500;
            $this->log(sprintf('[%s] END %s %s status=%d duration_ms=%d', $rid, $request->method, $request->path, $status, $dur));
        }

        return $response;
    }
}
