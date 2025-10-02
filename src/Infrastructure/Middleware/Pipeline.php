<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;

final class Pipeline
{
    /** @var list<MiddlewareInterface> */
    private array $stack = [];

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->stack[] = $middleware;
        return $this;
    }

    /** @param callable(Request): Response $destination */
    public function process(Request $request, callable $destination): Response
    {
        $runner = array_reduce(
            array_reverse($this->stack),
            function (callable $next, MiddlewareInterface $middleware) {
                return function (Request $req) use ($middleware, $next): Response {
                    return $middleware->process($req, $next);
                };
            },
            $destination
        );

        return $runner($request);
    }
}
