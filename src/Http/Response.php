<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        public int $status = 200,
        public string $body = '',
        /** @var array<string,string> */
        public array $headers = []
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            status: $status,
            body: json_encode($data, JSON_UNESCAPED_UNICODE),
            headers: ['Content-Type' => 'application/json']
        );
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self(
            status: $status,
            body: '',
            headers: ['Location' => $location]
        );
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }
}
